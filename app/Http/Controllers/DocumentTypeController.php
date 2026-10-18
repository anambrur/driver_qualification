<?php

namespace App\Http\Controllers;

use App\DataTables\Settings\DocumentTypeTable;
use App\Http\Controllers\Concerns\AuditsLookups;
use App\Models\DocumentType;
use App\Models\PlatformUser;
use App\Tenancy\CompanyScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends Controller
{
    use AuditsLookups;

    /**
     * Display a listing of document types.
     */
    public function index(Request $request)
    {
        try {
            // The platform manages the list (LKP-01); a company only switches types off for itself
            $user = $request->user();
            $platform = $user instanceof PlatformUser;
            $company = $platform ? null : $user->company;

            if ($request->ajax()) {
                return (new DocumentTypeTable(
                    $request,
                    $company ? $company->disabledDocumentTypes()->pluck('document_types.id')->all() : [],
                    $user->can('platform.settings.manage'),
                    $company && $user->can('document-types.toggle'),
                ))->json();
            }

            $modules = DocumentType::getModules();

            $showCompanyColumn = $company !== null;

            return view('admin.settings.document-types.index', compact('modules', 'showCompanyColumn', 'platform'));
        } catch (Exception $e) {
            Log::error('Error fetching document types: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'draw' => intval($request->input('draw', 1)),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'Failed to load document types.',
                ], 500);
            }

            return redirect()->back()->withErrors([
                'system_error' => 'Failed to load document types. Please try again.',
            ]);
        }
    }

    /**
     * Store a newly created document type.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:document_types,name',
                'module' => 'required|string|in:'.implode(',', array_keys(DocumentType::getModules())),
                'status' => 'required|boolean',
            ], [
                'name.required' => 'Document type name is required.',
                'name.unique' => 'This document type name already exists.',
                'module.required' => 'Module selection is required.',
                'module.in' => 'Please select a valid module.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            $documentType = DocumentType::create([
                'name' => $request->name,
                'module' => $request->module,
                'status' => $request->status ?? true,
            ]);

            $this->auditLookup('created', $documentType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document type created successfully!',
                'data' => $documentType,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error creating document type: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except('_token'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create document type. Please try again.',
            ], 500);
        }
    }

    /**
     * Show the specified document type.
     */
    public function show($id)
    {
        try {
            $documentType = DocumentType::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $documentType,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching document type: '.$e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document type not found.',
            ], 404);
        }
    }

    /**
     * Update the specified document type.
     */
    public function update(Request $request, $id)
    {
        try {
            $documentType = DocumentType::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:document_types,name,'.$id,
                'module' => 'required|string|in:'.implode(',', array_keys(DocumentType::getModules())),
                'status' => 'required|boolean',
            ], [
                'name.required' => 'Document type name is required.',
                'name.unique' => 'This document type name already exists.',
                'module.required' => 'Module selection is required.',
                'module.in' => 'Please select a valid module.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            $documentType->update([
                'name' => $request->name,
                'module' => $request->module,
                'status' => $request->status,
            ]);

            $this->auditLookup('updated', $documentType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document type updated successfully!',
                'data' => $documentType,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error updating document type: '.$e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id,
                'request_data' => $request->except('_token'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update document type. Please try again.',
            ], 500);
        }
    }

    /**
     * Remove the specified document type.
     */
    public function destroy($id)
    {
        try {
            $documentType = DocumentType::findOrFail($id);

            // Deleting cascades to every tenant's uploaded documents of this type
            if ($documentType->driverComplianceDocuments()->withoutGlobalScope(CompanyScope::class)->exists()
                || $documentType->vehicleDocuments()->withoutGlobalScope(CompanyScope::class)->exists()
                || $documentType->trailerDocuments()->withoutGlobalScope(CompanyScope::class)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete document type because it has associated documents. Deactivate it instead.',
                ], 400);
            }

            DB::beginTransaction();

            $documentType->delete();

            $this->auditLookup('deleted', $documentType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document type deleted successfully!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error deleting document type: '.$e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document type. Please try again.',
            ], 500);
        }
    }

    /**
     * Toggle document type status.
     */
    public function toggleStatus($id)
    {
        try {
            DB::beginTransaction();

            $documentType = DocumentType::findOrFail($id);
            $documentType->update([
                'status' => ! $documentType->status,
            ]);

            $this->auditLookup('updated', $documentType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully!',
                'data' => [
                    'status' => $documentType->status,
                    'status_label' => $documentType->status_label,
                ],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error toggling document type status: '.$e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status. Please try again.',
            ], 500);
        }
    }

    /**
     * Switch a global document type off (or back on) for the current user's company only.
     */
    public function toggleForCompany(Request $request, $id)
    {
        $documentType = DocumentType::findOrFail($id);
        $company = $request->user()->company;

        if (! $company) {
            abort(403, 'You need a company to change its document requirements.');
        }

        try {
            $changes = $company->disabledDocumentTypes()->toggle($documentType->id);
            $enabled = $changes['attached'] === [];

            return response()->json([
                'success' => true,
                'message' => $enabled
                    ? 'Document type switched on for your company.'
                    : 'Document type switched off for your company.',
                'data' => ['enabled' => $enabled],
            ]);
        } catch (Exception $e) {
            Log::error('Error toggling document type for company: '.$e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id,
                'company_id' => $company->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update your company setting. Please try again.',
            ], 500);
        }
    }

    /**
     * Get document types by module (for dropdowns).
     */
    public function getByModule(Request $request)
    {
        try {
            $request->validate([
                'module' => 'required|string',
            ]);

            $documentTypes = DocumentType::active()
                ->byModule($request->module)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => $documentTypes,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching document types by module: '.$e->getMessage(), [
                'exception' => $e,
                'module' => $request->module,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load document types.',
            ], 500);
        }
    }
}
