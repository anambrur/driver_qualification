<?php

namespace App\Http\Controllers;

use App\DataTables\Documents\DocumentTable;
use App\Http\Requests\Documents\MoveDocumentRequest;
use App\Http\Requests\Documents\UploadDocumentsRequest;
use App\Models\Document;
use App\Models\Folder;
use App\Services\Documents\DocumentService;
use App\Services\Documents\FolderBrowser;
use App\Support\Ui\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents filed in folders: the folder's list, upload, move, download and delete.
 */
class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}

    public function index(Request $request, Folder $folder): JsonResponse
    {
        Gate::authorize('view', $folder);

        return (new DocumentTable($request, $folder))->json();
    }

    public function store(UploadDocumentsRequest $request, Folder $folder): RedirectResponse
    {
        $uploaded = $this->documents->upload($folder, array_values((array) $request->file('files')), Arr::except($request->validated(), 'files'), $request->user());
        Toast::success($uploaded->count() === 1 ? 'Document uploaded.' : "{$uploaded->count()} documents uploaded.");

        return redirect()->to(FolderBrowser::url($folder));
    }

    public function move(MoveDocumentRequest $request, Document $document): RedirectResponse
    {
        $target = $request->target();
        Gate::authorize('view', $target);

        try {
            $this->documents->move($document, $target);
        } catch (ValidationException $e) {
            Toast::error($e->validator->errors()->first());

            return redirect()->to(FolderBrowser::url($document->folder ?? $target))->withErrors($e->errors());
        }

        Toast::success("Moved to “{$target->label()}”.");

        return redirect()->to(FolderBrowser::url($target));
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->documents->download($document, $request->user());
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $this->documents->delete($document);
        Toast::success('Document deleted.');

        return redirect()->to(FolderBrowser::url($document->folder));
    }
}
