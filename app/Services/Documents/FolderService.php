<?php

namespace App\Services\Documents;

use App\Models\Driver;
use App\Models\Folder;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Folders (08 §8, req 2.1–2.2). Each driver (DQ file), vehicle and trailer has one system folder,
 * created in the transaction that creates the entity. Custom folders nest up to MAX_DEPTH levels,
 * take their parent's binding, and have unique names among their siblings.
 */
class FolderService
{
    public function ensureSystemFolder(Driver|Vehicle|Trailer $entity): Folder
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('FolderService::ensureSystemFolder must run inside the transaction that creates the entity.');
        }

        $column = $entity->getForeignKey();

        return Folder::query()->where('system_key', Folder::BINDINGS[$column])->where($column, $entity->id)->first()
            ?? Folder::query()->create([
                'company_id' => $entity->company_id,
                'name' => Folder::systemName($entity),
                'system_key' => Folder::BINDINGS[$column],
                $column => $entity->id,
            ]);
    }

    /**
     * A custom folder below $parent, or a company-level folder when $parent is null.
     */
    public function create(?Folder $parent, string $name, User $actor): Folder
    {
        return DB::transaction(function () use ($parent, $name, $actor) {
            if ($parent !== null) {
                $parent = Folder::query()->lockForUpdate()->findOrFail($parent->id);

                if ($this->depth($parent) >= Folder::MAX_DEPTH) {
                    throw ValidationException::withMessages(['name' => 'Folders can be nested '.Folder::MAX_DEPTH.' levels deep.']);
                }
            }

            $this->assertNameFree($parent?->id, $name);

            return $this->unique(fn () => Folder::query()->create([
                'parent_id' => $parent?->id,
                'name' => $name,
                'created_by_user_id' => $actor->id,
            ] + ($parent?->bindingAttributes() ?? [])));
        });
    }

    public function rename(Folder $folder, string $name): Folder
    {
        return DB::transaction(function () use ($folder, $name) {
            $folder = Folder::query()->lockForUpdate()->findOrFail($folder->id);
            $this->assertCustom($folder, 'renamed');
            $this->assertNameFree($folder->parent_id, $name, $folder->id);

            return $this->unique(fn () => tap($folder)->update(['name' => $name]));
        });
    }

    /**
     * Only an empty custom folder can be deleted. Its trashed documents keep pointing at it.
     */
    public function delete(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            $folder = Folder::query()->lockForUpdate()->findOrFail($folder->id);
            $this->assertCustom($folder, 'deleted');

            if ($folder->children()->exists() || $folder->documents()->exists()) {
                throw ValidationException::withMessages(['folder' => 'Only an empty folder can be deleted. Move or delete its folders and documents first.']);
            }

            $folder->delete();
        });
    }

    /**
     * The live folders of one binding (or the company-level ones), for the tree and breadcrumbs.
     *
     * @param  array{0: string, 1: int}|null  $binding
     * @return Collection<int, Folder>
     */
    public function tree(?array $binding): Collection
    {
        return Folder::query()->boundTo($binding)->orderBy('name')->get();
    }

    /**
     * $folder and its ancestors, root first, taken from the already loaded tree.
     *
     * @param  Collection<int, Folder>  $tree
     * @return list<Folder>
     */
    public function breadcrumb(Collection $tree, Folder $folder): array
    {
        $byId = $tree->keyBy('id');
        $path = [];

        for ($current = $folder; $current !== null; $current = $byId->get($current->parent_id)) {
            array_unshift($path, $current);
        }

        return $path;
    }

    /**
     * 1 for a root folder.
     */
    private function depth(Folder $folder): int
    {
        $depth = 1;

        for ($parentId = $folder->parent_id; $parentId !== null; $depth++) {
            $parentId = Folder::query()->whereKey($parentId)->value('parent_id');
        }

        return $depth;
    }

    private function assertCustom(Folder $folder, string $verb): void
    {
        if ($folder->isSystem()) {
            throw ValidationException::withMessages(['folder' => "A standard folder can't be {$verb}."]);
        }
    }

    private function assertNameFree(?int $parentId, string $name, ?int $exceptId = null): void
    {
        $taken = Folder::query()
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $parentId))
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($taken) {
            $this->nameTaken();
        }
    }

    /**
     * The unique index backs the sibling check against a parallel create or rename.
     *
     * @param  callable(): Folder  $write
     */
    private function unique(callable $write): Folder
    {
        try {
            return $write();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $this->nameTaken();
            }

            throw $e;
        }
    }

    private function nameTaken(): never
    {
        throw ValidationException::withMessages(['name' => 'A folder with this name already exists here.']);
    }
}
