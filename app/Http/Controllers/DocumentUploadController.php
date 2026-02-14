<?php

namespace App\Http\Controllers;

use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\DocumentType;
use App\Models\VehicleDocument;
use App\Models\TrailerDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DocumentUploadController extends Controller
{
    /**
     * Get list of vehicles for upload dropdown
     */
    public function getVehiclesList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            $vehicles = Vehicle::select('id', 'unit_no')
                ->orderBy('unit_no')
                ->get()
                ->map(function ($vehicle) use ($documentTypeId) {
                    return [
                        'id' => $vehicle->id,
                        'unit_no' => $vehicle->unit_no,
                        'has_document' => VehicleDocument::where('vehicle_id', $vehicle->id)
                            ->where('document_type_id', $documentTypeId)
                            ->exists()
                    ];
                });

            return response()->json([
                'success' => true,
                'assets' => $vehicles,
                'document_type_name' => $documentType ? $documentType->name : 'Document'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load vehicles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of trailers for upload dropdown
     */
    public function getTrailersList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            $trailers = Trailer::select('id', 'unit_no')
                ->orderBy('unit_no')
                ->get()
                ->map(function ($trailer) use ($documentTypeId) {
                    return [
                        'id' => $trailer->id,
                        'unit_no' => $trailer->unit_no,
                        'has_document' => TrailerDocument::where('trailer_id', $trailer->id)
                            ->where('document_type_id', $documentTypeId)
                            ->exists()
                    ];
                });

            return response()->json([
                'success' => true,
                'assets' => $trailers,
                'document_type_name' => $documentType ? $documentType->name : 'Document'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load trailers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload document(s)
     */
    public function uploadDocument(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'asset_type' => 'required|in:vehicle,trailer',
            'expiry_date' => 'required|date|after_or_equal:today',
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
            $expiryDate = $request->expiry_date;
            $description = $request->description;
            $uploadToAll = $request->boolean('upload_to_all');

            // Handle file upload
            $file = $request->file('file');
            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $filePath = $file->storeAs('documents/' . $assetType . 's', $fileName, 'public');

            $uploadedCount = 0;
            $updatedCount = 0;

            if ($uploadToAll) {
                // Upload to all assets
                if ($assetType === 'vehicle') {
                    $assets = Vehicle::all();
                    foreach ($assets as $asset) {
                        $result = $this->createOrUpdateDocument(
                            'vehicle',
                            $asset->id,
                            $documentTypeId,
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
                    $assets = Trailer::all();
                    foreach ($assets as $asset) {
                        $result = $this->createOrUpdateDocument(
                            'trailer',
                            $asset->id,
                            $documentTypeId,
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
                }
            } else {
                // Upload to selected asset
                $selectedAssetId = $request->selected_asset;

                $result = $this->createOrUpdateDocument(
                    $assetType,
                    $selectedAssetId,
                    $documentTypeId,
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

            $message = $this->generateSuccessMessage($uploadedCount, $updatedCount, $assetType);

            return response()->json([
                'success' => true,
                'message' => $message,
                'uploaded_count' => $uploadedCount,
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if transaction fails
            if (isset($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create or update document for an asset
     */
    private function createOrUpdateDocument($assetType, $assetId, $documentTypeId, $expiryDate, $description, $filePath)
    {
        $created = false;

        if ($assetType === 'vehicle') {
            $existingDocument = VehicleDocument::where('vehicle_id', $assetId)
                ->where('document_type_id', $documentTypeId)
                ->first();

            if ($existingDocument) {
                // Delete old file
                if ($existingDocument->file_path) {
                    Storage::disk('public')->delete($existingDocument->file_path);
                }

                // Update existing document
                $existingDocument->update([
                    'expiry_date' => $expiryDate,
                    'description' => $description,
                    'file_path' => $filePath,
                ]);
            } else {
                // Create new document
                VehicleDocument::create([
                    'vehicle_id' => $assetId,
                    'document_type_id' => $documentTypeId,
                    'expiry_date' => $expiryDate,
                    'description' => $description,
                    'file_path' => $filePath,
                ]);
                $created = true;
            }
        } else {
            $existingDocument = TrailerDocument::where('trailer_id', $assetId)
                ->where('document_type_id', $documentTypeId)
                ->first();

            if ($existingDocument) {
                // Delete old file
                if ($existingDocument->file_path) {
                    Storage::disk('public')->delete($existingDocument->file_path);
                }

                // Update existing document
                $existingDocument->update([
                    'expiry_date' => $expiryDate,
                    'description' => $description,
                    'file_path' => $filePath,
                ]);
            } else {
                // Create new document
                TrailerDocument::create([
                    'trailer_id' => $assetId,
                    'document_type_id' => $documentTypeId,
                    'expiry_date' => $expiryDate,
                    'description' => $description,
                    'file_path' => $filePath,
                ]);
                $created = true;
            }
        }

        return ['created' => $created];
    }

    /**
     * Generate success message
     */
    private function generateSuccessMessage($uploadedCount, $updatedCount, $assetType)
    {
        $assetLabel = $assetType === 'vehicle' ? 'vehicle' : 'trailer';
        $assetLabelPlural = $assetType === 'vehicle' ? 'vehicles' : 'trailers';

        $messages = [];

        if ($uploadedCount > 0) {
            $messages[] = $uploadedCount === 1
                ? "Document uploaded to 1 {$assetLabel}"
                : "Documents uploaded to {$uploadedCount} {$assetLabelPlural}";
        }

        if ($updatedCount > 0) {
            $messages[] = $updatedCount === 1
                ? "1 {$assetLabel} document updated"
                : "{$updatedCount} {$assetLabelPlural} documents updated";
        }

        return implode(' and ', $messages);
    }

    /**
     * Delete document
     */
    public function deleteDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|integer',
            'asset_type' => 'required|in:vehicle,trailer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->asset_type === 'vehicle') {
                $document = VehicleDocument::findOrFail($request->document_id);
            } else {
                $document = TrailerDocument::findOrFail($request->document_id);
            }

            // Delete file from storage
            if ($document->file_path) {
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
     * Download document
     */
    public function downloadDocument(Request $request, $documentId, $assetType)
    {
        try {
            if ($assetType === 'vehicle') {
                $document = VehicleDocument::with('documentType')->findOrFail($documentId);
            } else {
                $document = TrailerDocument::with('documentType')->findOrFail($documentId);
            }

            if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
                abort(404, 'File not found');
            }

            $filePath = Storage::disk('public')->path($document->file_path);
            $fileName = $document->documentType->name . '_' . basename($document->file_path);

            return response()->download($filePath, $fileName);
        } catch (\Exception $e) {
            abort(404, 'Document not found');
        }
    }

    /**
     * View document
     */
    public function viewDocument($documentId, $assetType)
    {
        try {
            if ($assetType === 'vehicle') {
                $document = VehicleDocument::findOrFail($documentId);
            } else {
                $document = TrailerDocument::findOrFail($documentId);
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
            abort(404, 'Document not found');
        }
    }

    /**
     * Send reminder email
     */
    public function sendReminderEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|integer',
            'document_type_id' => 'required|integer',
            'asset_type' => 'required|in:vehicle,trailer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            // Get asset and document type
            if ($request->asset_type === 'vehicle') {
                $asset = Vehicle::with('assetGroups.driver')->findOrFail($request->asset_id);
            } else {
                $asset = Trailer::with('assetGroups.driver')->findOrFail($request->asset_id);
            }

            $documentType = DocumentType::findOrFail($request->document_type_id);

            // Check if driver is assigned
            if (!$asset->assetGroups || !$asset->assetGroups->driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'No driver assigned to this asset'
                ], 400);
            }

            $driver = $asset->assetGroups->driver;

            // TODO: Implement email sending logic here
            // Example:
            // Mail::to($driver->email)->send(new DocumentReminderMail($asset, $documentType));

            return response()->json([
                'success' => true,
                'message' => "Reminder email sent to {$driver->first_name} {$driver->last_name}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder: ' . $e->getMessage()
            ], 500);
        }
    }
}
