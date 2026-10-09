<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Driver;
use App\Models\DocumentType;
use App\Models\DriverComplianceDocument;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DriverDocumentUploadController extends Controller
{
    use CompanyFilterTrait;

    /**
     * DCMP-01: compliance documents live on the private `local` disk under a random name and are
     * only served by viewDocument()/downloadDocument(). Files uploaded before that are still on the
     * `public` disk until the 2026_10_09_000900 migration moves them, so reads and deletes check both.
     */
    private const DISK = 'local';

    private const LEGACY_DISK = 'public';

    private const FILE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
    ];

    /**
     * Get list of drivers for upload dropdown (filtered by company)
     */
    public function getDriversList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            // The company "upload to all" applies to; a super-admin works on the company of the
            // driver the modal was opened for, and only sees that company's drivers.
            $company = $this->uploadCompany($request->integer('driver_id') ?: null);

            // Apply company filter and load has_document in one query (avoid N+1 exists)
            $driversQuery = Driver::select('drivers.id', 'drivers.first_name', 'drivers.last_name')
                ->withExists([
                    'documents as has_document' => function ($query) use ($documentTypeId) {
                        $query->where('document_type_id', $documentTypeId);
                    },
                ]);
            $driversQuery = $this->applyCompanyFilter($driversQuery);
            if ($this->isSuperAdmin() && $company) {
                $driversQuery->where('drivers.company_id', $company->id);
            }

            $drivers = $driversQuery->orderBy('drivers.first_name')
                ->orderBy('drivers.last_name')
                ->get()
                ->map(function ($driver) {
                    return [
                        'id' => $driver->id,
                        'full_name' => $driver->first_name . ' ' . $driver->last_name,
                        'has_document' => (bool) $driver->has_document,
                    ];
                });

            // Check if user has any drivers
            if ($drivers->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'assets' => [],
                    'document_type_name' => $documentType ? $documentType->name : 'Document',
                    'company' => $this->companyPayload($company),
                    'message' => 'No drivers found for your company'
                ]);
            }

            return response()->json([
                'success' => true,
                'assets' => $drivers,
                'document_type_name' => $documentType ? $documentType->name : 'Document',
                'company' => $this->companyPayload($company),
                'total' => $drivers->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load drivers for compliance upload', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load drivers.'
            ], 500);
        }
    }

    /**
     * Upload document(s) with company validation
     */
    public function uploadDocument(Request $request)
    {
        $isSuperAdmin = $this->isSuperAdmin();

        // Validate request
        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'asset_type' => 'required|in:driver',
            'file_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'description' => 'nullable|string|max:500',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480', // 20MB max
            'upload_to_all' => 'nullable|boolean',
            'selected_asset' => 'required_without:upload_to_all',
            // Tenants always upload to their own company; a super-admin names the company
            'company_id' => $isSuperAdmin
                ? [Rule::requiredIf($request->boolean('upload_to_all')), 'nullable', 'integer', Rule::exists('companies', 'id')]
                : ['nullable'],
        ], [
            'company_id.required' => 'Choose the company whose drivers should get this document.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $uploadToAll = $request->boolean('upload_to_all');

        if ($uploadToAll) {
            // Upload to all active drivers of one company: the tenant's own, or the one a
            // super-admin chose (decided 2026-10-10)
            $companyId = $isSuperAdmin ? $request->integer('company_id') : $this->getUserCompanyId();
            $drivers = Driver::where('company_id', $companyId)->where('status', 'active')->get();

            if ($drivers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'There are no active drivers to upload this document to.'
                ], 422);
            }
        } else {
            // Upload to selected driver (validate company access first)
            $driver = Driver::whereKey($request->selected_asset)->first();
            abort_unless($driver instanceof Driver, 404, 'Driver not found.');
            $this->authorizeCompanyAccess($driver, 'You do not have permission to upload documents for this driver.');
            $drivers = collect([$driver]);
        }

        $filePath = null;
        $replacedPaths = [];
        $uploadedCount = 0;
        $updatedCount = 0;

        try {
            DB::beginTransaction();

            $documentTypeId = $request->document_type_id;
            $fileDate = $request->file_date;
            $expiryDate = $request->expiry_date;
            $description = $request->description;

            // Random name with the extension of the content (DCMP-04). One file is shared by
            // every driver of an "upload to all" (DCMP-02: see deleteFileIfUnused()).
            $filePath = $request->file('file')->store('documents/drivers', self::DISK);
            if (! $filePath) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            foreach ($drivers as $driver) {
                $result = $this->createOrUpdateDocumentWithValidation(
                    $driver->id,
                    $documentTypeId,
                    $fileDate,
                    $expiryDate,
                    $description,
                    $filePath
                );

                if ($result['created']) {
                    $uploadedCount++;
                } else {
                    $updatedCount++;
                }

                if ($result['replaced_path']) {
                    $replacedPaths[] = $result['replaced_path'];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if transaction fails
            if ($filePath) {
                Storage::disk(self::DISK)->delete($filePath);
            }

            Log::error('Failed to upload driver compliance document', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document.'
            ], 500);
        }

        // Old files are only removed once the new rows are committed, and only if no other
        // driver's document still points at them.
        foreach (array_unique($replacedPaths) as $path) {
            $this->deleteFileIfUnused($path);
        }

        $message = $this->generateSuccessMessage($uploadedCount, $updatedCount);

        return response()->json([
            'success' => true,
            'message' => $message,
            'uploaded_count' => $uploadedCount,
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * Create or update document for a driver with validation
     */
    private function createOrUpdateDocumentWithValidation($driverId, $documentTypeId, $fileDate, $expiryDate, $description, $filePath)
    {
        $created = false;
        $replacedPath = null;

        // Double-check driver belongs to user's company
        $driver = Driver::findOrFail($driverId);
        if (!$this->userHasAccess($driver)) {
            throw new \Exception('Unauthorized access to driver');
        }

        $existingDocument = DriverComplianceDocument::where('driver_id', $driverId)
            ->where('document_type_id', $documentTypeId)
            ->first();

        if ($existingDocument) {
            // The old file is deleted by the caller after commit, if no other document uses it
            if ($existingDocument->file_path && $existingDocument->file_path !== $filePath) {
                $replacedPath = $existingDocument->file_path;
            }

            // Update existing document
            $existingDocument->update([
                'file_date' => $fileDate,
                'expiry_date' => $expiryDate,
                'description' => $description,
                'file_path' => $filePath,
            ]);
        } else {
            // Create new document
            DriverComplianceDocument::create([
                'driver_id' => $driverId,
                'document_type_id' => $documentTypeId,
                'file_date' => $fileDate,
                'expiry_date' => $expiryDate,
                'description' => $description,
                'file_path' => $filePath,
            ]);
            $created = true;
        }

        return ['created' => $created, 'replaced_path' => $replacedPath];
    }

    /**
     * Delete document with company validation
     */
    public function deleteDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $document = $this->findAuthorizedDocument($request->document_id, 'You do not have permission to delete documents for this driver.');

        try {
            $filePath = $document->file_path;

            // Delete document record
            $document->delete();

            // Delete file from storage, unless another driver's document shares it (DCMP-02)
            if ($filePath) {
                $this->deleteFileIfUnused($filePath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete driver compliance document', ['exception' => $e, 'document_id' => $document->id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document.'
            ], 500);
        }
    }

    /**
     * Download document with company validation
     */
    public function downloadDocument(Request $request, $documentId)
    {
        $document = $this->findAuthorizedDocument($documentId, 'You do not have permission to download documents for this driver.');
        $disk = $this->diskHolding($document->file_path);

        $fileName = $document->documentType->name . '_' . basename($document->file_path);

        return Storage::disk($disk)->download($document->file_path, $fileName, self::FILE_HEADERS);
    }

    /**
     * View document with company validation
     */
    public function viewDocument($documentId)
    {
        $document = $this->findAuthorizedDocument($documentId, 'You do not have permission to view documents for this driver.');
        $disk = $this->diskHolding($document->file_path);

        return Storage::disk($disk)->response($document->file_path, null, self::FILE_HEADERS);
    }

    private function isSuperAdmin(): bool
    {
        $this->resolveCompanyContext();

        return (bool) $this->resolvedSuperAdmin;
    }

    /**
     * The company the upload modal works on: a tenant's own company, or for a super-admin the
     * company of the given driver (null when there is none).
     */
    private function uploadCompany(?int $driverId): ?Company
    {
        if (! $this->isSuperAdmin()) {
            return Company::find($this->getUserCompanyId());
        }

        return $driverId ? Company::find(Driver::whereKey($driverId)->value('company_id')) : null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function companyPayload(?Company $company): ?array
    {
        return $company ? ['id' => $company->id, 'name' => (string) $company->company_name] : null;
    }

    /**
     * Load a document the user may act on: 404 if it or its driver is gone (a soft-deleted
     * driver loads as null, DCMP-07), 403 if the driver belongs to another company.
     */
    private function findAuthorizedDocument($documentId, string $message): DriverComplianceDocument
    {
        $document = DriverComplianceDocument::with(['documentType', 'driver'])->whereKey($documentId)->first();
        abort_unless($document?->driver !== null, 404, 'Document not found');

        $this->authorizeCompanyAccess($document->driver, $message);

        return $document;
    }

    /**
     * The disk the file is on: the private disk, or the public one for files not yet moved.
     */
    private function diskHolding(?string $filePath): string
    {
        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if ($filePath && Storage::disk($disk)->exists($filePath)) {
                return $disk;
            }
        }

        abort(404, 'File not found');
    }

    /**
     * Delete a file from both disks once no document points at it any more. "Upload to all"
     * gives every driver the same file (DCMP-02).
     */
    private function deleteFileIfUnused(string $filePath): void
    {
        if (DriverComplianceDocument::where('file_path', $filePath)->exists()) {
            return;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            Storage::disk($disk)->delete($filePath);
        }
    }

    /**
     * Generate success message
     */
    private function generateSuccessMessage($uploadedCount, $updatedCount)
    {
        $messages = [];

        if ($uploadedCount > 0) {
            $messages[] = $uploadedCount === 1
                ? "Document uploaded to 1 driver"
                : "Documents uploaded to {$uploadedCount} drivers";
        }

        if ($updatedCount > 0) {
            $messages[] = $updatedCount === 1
                ? "1 driver document updated"
                : "{$updatedCount} driver documents updated";
        }

        return implode(' and ', $messages);
    }
}
