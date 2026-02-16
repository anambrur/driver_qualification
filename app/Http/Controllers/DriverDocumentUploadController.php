<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DocumentType;
use App\Models\DriverComplianceDocument;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DriverDocumentUploadController extends Controller
{
    use CompanyFilterTrait;

    /**
     * Get list of drivers for upload dropdown (filtered by company)
     */
    public function getDriversList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            // Apply company filter to drivers query
            $driversQuery = Driver::select('drivers.id', 'drivers.first_name', 'drivers.last_name');
            $driversQuery = $this->applyCompanyFilter($driversQuery);

            $drivers = $driversQuery->orderBy('drivers.first_name')
                ->orderBy('drivers.last_name')
                ->get()
                ->map(function ($driver) use ($documentTypeId) {
                    return [
                        'id' => $driver->id,
                        'full_name' => $driver->first_name . ' ' . $driver->last_name,
                        'has_document' => DriverComplianceDocument::where('driver_id', $driver->id)
                            ->where('document_type_id', $documentTypeId)
                            ->exists()
                    ];
                });

            // Check if user has any drivers
            if ($drivers->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'assets' => [],
                    'document_type_name' => $documentType ? $documentType->name : 'Document',
                    'message' => 'No drivers found for your company'
                ]);
            }

            return response()->json([
                'success' => true,
                'assets' => $drivers,
                'document_type_name' => $documentType ? $documentType->name : 'Document',
                'total' => $drivers->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load drivers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload document(s) with company validation
     */
    public function uploadDocument(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'asset_type' => 'required|in:driver',
            'file_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'description' => 'nullable|string|max:500',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480', // 20MB max
            'upload_to_all' => 'nullable|boolean',
            'selected_asset' => 'required_without:upload_to_all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $documentTypeId = $request->document_type_id;
            $assetType = $request->asset_type;
            $fileDate = $request->file_date;
            $expiryDate = $request->expiry_date;
            $description = $request->description;
            $uploadToAll = $request->boolean('upload_to_all');

            // Handle file upload
            $file = $request->file('file');
            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $filePath = $file->storeAs('documents/drivers', $fileName, 'public');

            $uploadedCount = 0;
            $updatedCount = 0;

            if ($uploadToAll) {
                // Upload to all drivers (filtered by company)
                $driversQuery = Driver::query();
                $driversQuery = $this->applyCompanyFilter($driversQuery);
                $drivers = $driversQuery->get();

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
                }
            } else {
                // Upload to selected driver (validate company access first)
                $selectedDriverId = $request->selected_asset;

                // Validate that the user has access to this driver
                $driver = Driver::findOrFail($selectedDriverId);
                $this->authorizeCompanyAccess($driver, 'You do not have permission to upload documents for this driver.');

                $result = $this->createOrUpdateDocumentWithValidation(
                    $selectedDriverId,
                    $documentTypeId,
                    $fileDate,
                    $expiryDate,
                    $description,
                    $filePath
                );

                if ($result['created']) {
                    $uploadedCount = 1;
                } else {
                    $updatedCount = 1;
                }
            }

            DB::commit();

            $message = $this->generateSuccessMessage($uploadedCount, $updatedCount);

            return response()->json([
                'success' => true,
                'message' => $message,
                'uploaded_count' => $uploadedCount,
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if transaction fails
            if (isset($filePath) && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create or update document for a driver with validation
     */
    private function createOrUpdateDocumentWithValidation($driverId, $documentTypeId, $fileDate, $expiryDate, $description, $filePath)
    {
        $created = false;

        // Double-check driver belongs to user's company
        $driver = Driver::findOrFail($driverId);
        if (!$this->userHasAccess($driver)) {
            throw new \Exception('Unauthorized access to driver');
        }

        $existingDocument = DriverComplianceDocument::where('driver_id', $driverId)
            ->where('document_type_id', $documentTypeId)
            ->first();

        if ($existingDocument) {
            // Delete old file
            if ($existingDocument->file_path && Storage::disk('public')->exists($existingDocument->file_path)) {
                Storage::disk('public')->delete($existingDocument->file_path);
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

        return ['created' => $created];
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

        try {
            $document = DriverComplianceDocument::with('driver')->findOrFail($request->document_id);

            // Validate user has access to the parent driver
            if ($document->driver) {
                $this->authorizeCompanyAccess($document->driver, 'You do not have permission to delete documents for this driver.');
            }

            // Delete file from storage
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // Delete document record
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download document with company validation
     */
    public function downloadDocument(Request $request, $documentId)
    {
        try {
            $document = DriverComplianceDocument::with(['documentType', 'driver'])->findOrFail($documentId);

            // Validate user has access to the parent driver
            if ($document->driver) {
                $this->authorizeCompanyAccess($document->driver, 'You do not have permission to download documents for this driver.');
            }

            if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
                abort(404, 'File not found');
            }

            $filePath = Storage::disk('public')->path($document->file_path);
            $fileName = $document->documentType->name . '_' . basename($document->file_path);

            return response()->download($filePath, $fileName);
        } catch (\Exception $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }
            abort(404, 'Document not found');
        }
    }

    /**
     * View document with company validation
     */
    public function viewDocument($documentId)
    {
        try {
            $document = DriverComplianceDocument::with('driver')->findOrFail($documentId);

            // Validate user has access to the parent driver
            if ($document->driver) {
                $this->authorizeCompanyAccess($document->driver, 'You do not have permission to view documents for this driver.');
            }

            if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
                abort(404, 'File not found');
            }

            $filePath = Storage::disk('public')->path($document->file_path);
            $mimeType = Storage::disk('public')->mimeType($document->file_path);

            return response()->file($filePath, [
                'Content-Type' => $mimeType
            ]);
        } catch (\Exception $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }
            abort(404, 'Document not found');
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
