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
            if ($request->ajax()) {
                $documentTypes = DocumentType::query();

                return DataTables::of($documentTypes)
                    ->addIndexColumn()
                    ->addColumn('module', function ($documentType) {
                        return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">' .
                            $documentType->module_label . '</span>';
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
                    ->addColumn('action', function ($documentType) {
                        return '<div class="flex items-center space-x-2">
                                <button type="button" onclick="editDocumentType(' . $documentType->id . ')" 
                                    class="inline-flex items-center justify-center w-8 h-8 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30" 
                                    title="Edit">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button type="button" onclick="deleteDocumentType(' . $documentType->id . ')" 
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30" 
                                    title="Delete">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                                <button type="button" onclick="toggleStatus(' . $documentType->id . ', ' . ($documentType->status ? '0' : '1') . ')" 
                                    class="inline-flex items-center justify-center w-8 h-8 ' . ($documentType->status ?  'text-green-600 border-green-200 dark:text-green-400 dark:border-green-800' : 'text-yellow-600 border-yellow-200 dark:text-yellow-400 dark:border-yellow-800') . ' border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800" 
                                    title="' . ($documentType->status ? 'Activate' : 'Deactivate') . '">
                                    <i class="fas ' . ($documentType->status ? 'fa-toggle-on' : 'fa-toggle-off') . ' text-xs"></i>
                                </button>
                            </div>';
                    })
                    ->rawColumns(['module', 'status', 'action'])
                    ->make(true);
            }

            $modules = DocumentType::getModules();

            return view('admin.settings.document-types.index', compact('modules'));
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
            DB::beginTransaction();

            $documentType = DocumentType::findOrFail($id);

            // Check if document type is in use (you can add this logic if needed)
            // if ($documentType->documents()->exists()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Cannot delete document type because it has associated documents.'
            //     ], 400);
            // }

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
