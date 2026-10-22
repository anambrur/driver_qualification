<?php

namespace App\DataTables\Documents;

use App\Models\Document;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * The live documents of one folder (folder browser). Searched by title.
 */
final class DocumentTable
{
    public function __construct(private readonly Request $request, private readonly Folder $folder) {}

    public function json(): JsonResponse
    {
        $user = $this->request->user();
        $canMove = (bool) $user?->can('documents.upload');
        $canDelete = (bool) $user?->can('documents.delete');

        $query = Document::query()
            ->where('folder_id', $this->folder->id)
            ->select(['id', 'company_id', 'folder_id', 'file_id', 'title', 'document_type_id', 'issued_on', 'expires_on', 'uploaded_by_user_id', 'created_at'])
            ->with(['file:id,path,size_bytes', 'documentType:id,name', 'uploadedBy:id,name']);

        $term = trim((string) data_get($this->request->input('search'), 'value', ''));
        if ($term !== '') {
            $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        return DataTables::eloquent($query)
            ->addColumn('document', fn (Document $document) => view('documents.partials.document-cell', ['document' => $document])->render())
            ->addColumn('type', fn (Document $document) => e($document->documentType->name ?? '—'))
            ->addColumn('dates', fn (Document $document) => e(collect([
                $document->issued_on ? 'Issued '.$document->issued_on->format('M d, Y') : null,
                $document->expires_on ? 'Expires '.$document->expires_on->format('M d, Y') : null,
            ])->filter()->implode(' · ') ?: '—'))
            ->addColumn('size', fn (Document $document) => $document->file ? self::size($document->file->size_bytes) : '—')
            ->addColumn('uploaded', fn (Document $document) => e(trim($document->created_at?->format('M d, Y').($document->uploadedBy ? ' · '.$document->uploadedBy->name : ''))))
            ->addColumn('action', fn (Document $document) => view('documents.partials.document-actions', [
                'document' => $document, 'canMove' => $canMove, 'canDelete' => $canDelete,
            ])->render())
            ->orderColumn('document', 'title $1')
            ->orderColumn('uploaded', 'created_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['document', 'type', 'dates', 'uploaded', 'action'])
            ->make(true);
    }

    /**
     * 1.2 MB. Number::fileSize needs intl, which production may not have (PO-20).
     */
    private static function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return number_format($bytes / 1024 ** $power, $power === 0 ? 0 : 1).' '.$units[$power];
    }
}
