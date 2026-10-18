<?php

namespace App\DataTables\Settings;

use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side document type list: managed by the platform, switched off per company (LKP-01).
 */
final class DocumentTypeTable
{
    /** @var list<int> */
    private readonly array $disabledIds;

    /**
     * @param  array<int, int|string>  $disabledIds  the company's switched-off types (empty on the platform)
     */
    public function __construct(
        private readonly Request $request,
        array $disabledIds,
        private readonly bool $canManage,
        private readonly bool $canOptOut,
    ) {
        $this->disabledIds = array_values(array_map('intval', $disabledIds));
    }

    public function json(): JsonResponse
    {
        $query = DocumentType::query()->select(['id', 'name', 'module', 'status', 'created_at']);

        $module = (string) $this->request->input('module', '');
        if (array_key_exists($module, DocumentType::getModules())) {
            $query->where('module', $module);
        }

        $status = (string) $this->request->input('status', '');
        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status === 'active');
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('module_label', fn (DocumentType $type) => $type->module_label)
            ->addColumn('status_badge', fn (DocumentType $type) => view('partials.ui.status-badge', ['status' => $type->status ? 'active' : 'inactive'])->render())
            ->addColumn('company_enabled', fn (DocumentType $type) => view('admin.settings.document-types.partials.company-badge', [
                'enabled' => ! in_array($type->id, $this->disabledIds, true),
            ])->render())
            ->editColumn('created_at', fn (DocumentType $type) => $type->created_at?->format('M d, Y'))
            ->addColumn('action', fn (DocumentType $type) => view('admin.settings.document-types.partials.row-actions', [
                'type' => $type,
                'enabled' => ! in_array($type->id, $this->disabledIds, true),
                'canManage' => $this->canManage,
                'canOptOut' => $this->canOptOut,
            ])->render())
            ->orderColumn('module_label', 'module $1')
            ->rawColumns(['status_badge', 'company_enabled', 'action'])
            ->make(true);
    }
}
