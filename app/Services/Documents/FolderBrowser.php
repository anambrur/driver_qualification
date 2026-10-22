<?php

namespace App\Services\Documents;

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Folder;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Gate;

/**
 * What the folder browser shows: an entity's folders (its Documents tab) or the company-level
 * folders (the Documents page), the open folder and its breadcrumb. Two queries.
 *
 * @phpstan-type Browser array{binding: array{0: string, 1: int}|null, tree: Collection<int, Folder>, children: BaseCollection<int|string, Collection<int, Folder>>, current: Folder|null, breadcrumb: list<Folder>, paths: array<int, string>, documentTypes: Collection<int, DocumentType>}
 */
class FolderBrowser
{
    public function __construct(private FolderService $folders, private TenantContext $tenant) {}

    /**
     * The entity page's Documents tab, or null when the user may not see folders.
     *
     * @return Browser|null
     */
    public function tab(Driver|Vehicle|Trailer $entity, ?int $folderId): ?array
    {
        return Gate::allows('folders.view') ? $this->build([$entity->getForeignKey(), $entity->id], $folderId) : null;
    }

    /**
     * @return Browser
     */
    public function forCompany(?int $folderId): array
    {
        return $this->build(null, $folderId);
    }

    /**
     * Where a folder is shown: its entity's Documents tab, or the company Documents page.
     */
    public static function url(Folder $folder): string
    {
        return match ($folder->binding()[0] ?? null) {
            'driver_id' => route('admin.driver.show', [$folder->driver_id, 'folder' => $folder->id]).'#documents',
            'vehicle_id' => route('admin.vehicle.show', [$folder->vehicle_id, 'folder' => $folder->id]).'#documents',
            'trailer_id' => route('admin.trailer.show', [$folder->trailer_id, 'folder' => $folder->id]).'#documents',
            default => route('documents.index', ['folder' => $folder->id]),
        };
    }

    /**
     * @param  array{0: string, 1: int}|null  $binding
     * @return Browser
     */
    private function build(?array $binding, ?int $folderId): array
    {
        $tree = $this->folders->tree($binding);
        $current = $folderId === null
            ? $tree->first(fn (Folder $folder) => $folder->parent_id === null)
            : ($tree->firstWhere('id', $folderId) ?? abort(404));
        $module = $binding === null ? null : substr($binding[0], 0, -3);

        return [
            'binding' => $binding,
            'tree' => $tree,
            'children' => $tree->groupBy(fn (Folder $folder) => $folder->parent_id ?? 0),
            'current' => $current,
            'breadcrumb' => $current === null ? [] : $this->folders->breadcrumb($tree, $current),
            // "DQ file / Medical": the move dialog's choices
            'paths' => $tree->mapWithKeys(fn (Folder $folder) => [$folder->id => collect($this->folders->breadcrumb($tree, $folder))
                ->map(fn (Folder $step) => $step->label())->implode(' / ')])->sort()->all(),
            'documentTypes' => DocumentType::query()->enabledForCompany($this->tenant->requireCompanyId())
                ->byModule($module)->orderBy('name')->get(['id', 'name', 'module']),
        ];
    }
}
