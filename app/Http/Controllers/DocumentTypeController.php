<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends Controller
{
    /**
     * Display a listing of document types.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $company = $user->hasRole('super-admin') ? null : $user->company;

            if ($request->ajax()) {
                $documentTypes = DocumentType::query();
                $disabledIds = $company ? $company->disabledDocumentTypes()->pluck('document_types.id')->all() : [];
                $canEdit = $user->can('document-types.edit');
                $canDelete = $user->can('document-types.delete');
                $canOptOut = $company && $user->can('companies.edit');

                return DataTables::of($documentTypes)
                    ->addIndexColumn()
                    ->addColumn('module', function ($documentType) {
                        return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">' .
                            e($documentType->module_label) . '</span>';
                    })
                    ->addColumn('status', function ($documentType) {
                        if ($documentType->status) {
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <i class="fas fa-check-circle mr-1"></i> Active
                                </span>';
                        } else {
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    <i class="fas fa-times-circle mr-1"></i> Inactive
                                </span>';
                        }
                    })
                    ->addColumn('created_at', function ($documentType) {
                        return $documentType->created_at->format('M d, Y h:i A');
                    })
                    ->addColumn('company_enabled', function ($documentType) use ($disabledIds) {
                        if (in_array($documentType->id, $disabledIds, true)) {
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    <i class="fas fa-ban mr-1"></i> Off
                                </span>';
                        }

                        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <i class="fas fa-check mr-1"></i> On
                            </span>';
                    })
                    ->addColumn('action', function ($documentType) use ($disabledIds, $canEdit, $canDelete, $canOptOut) {
                        $buttons = '';

                        if ($canOptOut) {
                            $enabled = ! in_array($documentType->id, $disabledIds, true);
                            $buttons .= '<button type="button" data-action="company-toggle" data-id="' . $documentType->id . '"
                                    class="inline-flex items-center px-3 py-1 text-sm rounded-md ' . ($enabled ? 'text-gray-700 bg-gray-100 hover:bg-gray-200' : 'text-green-700 bg-green-100 hover:bg-green-200') . '"
                                    title="' . ($enabled ? 'Stop requiring this document for your company' : 'Require this document for your company') . '">
                                    <i class="fas ' . ($enabled ? 'fa-toggle-on' : 'fa-toggle-off') . ' mr-1"></i> ' . ($enabled ? 'Switch off' : 'Switch on') . '
                                </button>';
                        }

                        return '<div class="flex items-center space-x-2">' . $buttons . '
                                ' . ($canEdit ? '<button type="button" onclick="editDocumentType(' . $documentType->id . ')" 
                                    class="inline-flex items-center justify-center w-8 h-8 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30" 
                                    title="Edit">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>' : '') . '
                                ' . ($canDelete ? '<button type="button" onclick="deleteDocumentType(' . $documentType->id . ')" 
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30" 
                                    title="Delete">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>' : '') . '
                                ' . ($canEdit ? '<button type="button" onclick="toggleStatus(' . $documentType->id . ', ' . ($documentType->status ? '0' : '1') . ')" 
                                    class="inline-flex items-center justify-center w-8 h-8 ' . ($documentType->status ?  'text-green-600 border-green-200 dark:text-green-400 dark:border-green-800' : 'text-yellow-600 border-yellow-200 dark:text-yellow-400 dark:border-yellow-800') . ' border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800" 
                                    title="' . ($documentType->status ? 'Activate' : 'Deactivate') . '">
                                    <i class="fas ' . ($documentType->status ? 'fa-toggle-on' : 'fa-toggle-off') . ' text-xs"></i>
                                </button>' : '') . '
                            </div>';
                    })
                    ->rawColumns(['module', 'status', 'company_enabled', 'action'])
                    ->make(true);
            }

            $modules = DocumentType::getModules();

            $showCompanyColumn = $company !== null;

            return view('admin.settings.document-types.index', compact('modules', 'showCompanyColumn'));
        } catch (Exception $e) {
            Log::error('Error fetching document types: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'draw' => intval($request->input('draw', 1)),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'Failed to load document types.'
                ], 500);
            }

            return redirect()->back()->withErrors([
                'system_error' => 'Failed to load document types. Please try again.'
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
                'module' => 'required|string|in:' . implode(',', array_keys(DocumentType::getModules())),
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
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $documentType = DocumentType::create([
                'name' => $request->name,
                'module' => $request->module,
                'status' => $request->status ?? true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document type created successfully!',
                'data' => $documentType
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error creating document type: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except('_token')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create document type. Please try again.'
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
                'data' => $documentType
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching document type: ' . $e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document type not found.'
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
                'name' => 'required|string|max:255|unique:document_types,name,' . $id,
                'module' => 'required|string|in:' . implode(',', array_keys(DocumentType::getModules())),
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
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $documentType->update([
                'name' => $request->name,
                'module' => $request->module,
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document type updated successfully!',
                'data' => $documentType
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error updating document type: ' . $e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id,
                'request_data' => $request->except('_token')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update document type. Please try again.'
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
            if ($documentType->driverComplianceDocuments()->exists()
                || $documentType->vehicleDocuments()->exists()
                || $documentType->trailerDocuments()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete document type because it has associated documents. Deactivate it instead.'
                ], 400);
            }

            DB::beginTransaction();

            $documentType->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document type deleted successfully!'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error deleting document type: ' . $e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document type. Please try again.'
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
                'status' => !$documentType->status
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully!',
                'data' => [
                    'status' => $documentType->status,
                    'status_label' => $documentType->status_label
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error toggling document type status: ' . $e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status. Please try again.'
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
            Log::error('Error toggling document type for company: ' . $e->getMessage(), [
                'exception' => $e,
                'document_type_id' => $id,
                'company_id' => $company->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update your company setting. Please try again.'
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
                'module' => 'required|string'
            ]);

            $documentTypes = DocumentType::active()
                ->byModule($request->module)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => $documentTypes
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching document types by module: ' . $e->getMessage(), [
                'exception' => $e,
                'module' => $request->module
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load document types.'
            ], 500);
        }
    }
}
