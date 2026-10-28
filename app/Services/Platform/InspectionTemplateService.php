<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use App\Models\PlatformUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pre-trip checklist versions (08 §9, LKP-01). A version an inspection used is never edited:
 * changes go into a new version. One active published version per inspection type; publishing
 * or activating a version deactivates the others. Item edits lock the template row and
 * InspectionService::start reads it with a shared lock, so an edit never races a first use.
 */
class InspectionTemplateService
{
    public function create(string $name): InspectionTemplate
    {
        $template = (new InspectionTemplate(['name' => $name, 'inspection_type' => 'pre_trip']))->forceFill(['version' => 1]);
        $template->save();

        return $template;
    }

    /**
     * @param  array{section: string, label: string, sort_order: int, is_required: bool}  $attributes
     */
    public function addItem(InspectionTemplate $template, array $attributes): InspectionTemplateItem
    {
        return DB::transaction(fn () => $this->lockEditable($template)->items()->create($attributes));
    }

    /**
     * @param  array{section: string, label: string, sort_order: int, is_required: bool}  $attributes
     */
    public function updateItem(InspectionTemplate $template, InspectionTemplateItem $item, array $attributes): void
    {
        DB::transaction(function () use ($template, $item, $attributes) {
            $this->lockEditable($template);
            $item->update($attributes);
        });
    }

    public function deleteItem(InspectionTemplate $template, InspectionTemplateItem $item): void
    {
        DB::transaction(function () use ($template, $item) {
            $this->lockEditable($template);
            $item->delete();
        });
    }

    public function publish(PlatformUser $actor, InspectionTemplate $template): void
    {
        DB::transaction(function () use ($actor, $template) {
            $template = $this->lock($template);

            if (! $template->isDraft()) {
                throw ValidationException::withMessages(['template' => 'This version is already published.']);
            }
            if (! $template->items()->exists()) {
                throw ValidationException::withMessages(['template' => 'Add at least one item before publishing.']);
            }

            $deactivated = $this->deactivateOthers($template);
            $template->forceFill(['published_at' => now(), 'is_active' => true])->save();
            AuditLog::record($actor, null, 'inspection_template.published', $template, ['version' => $template->version, 'deactivated' => $deactivated]);
        });
    }

    /**
     * The next version of this template, as a draft with the items copied. An existing draft of
     * the template is returned instead of a second one.
     */
    public function newVersion(InspectionTemplate $template): InspectionTemplate
    {
        return DB::transaction(function () use ($template) {
            $versions = InspectionTemplate::query()->where('name', $template->name)->lockForUpdate()->get();

            if ($draft = $versions->first(fn (InspectionTemplate $version) => $version->isDraft())) {
                return $draft;
            }

            $copy = (new InspectionTemplate(['name' => $template->name, 'inspection_type' => $template->inspection_type]))
                ->forceFill(['version' => $versions->max('version') + 1]);
            $copy->save();
            $copy->items()->createMany($template->items()->get(['section', 'label', 'sort_order', 'is_required'])->toArray());

            return $copy;
        });
    }

    public function activate(PlatformUser $actor, InspectionTemplate $template): void
    {
        DB::transaction(function () use ($actor, $template) {
            $template = $this->lock($template);

            if ($template->isDraft() || $template->is_active) {
                throw ValidationException::withMessages(['template' => 'Only an inactive published version can be activated.']);
            }

            $deactivated = $this->deactivateOthers($template);
            $template->forceFill(['is_active' => true])->save();
            AuditLog::record($actor, null, 'inspection_template.activated', $template, ['version' => $template->version, 'deactivated' => $deactivated]);
        });
    }

    /**
     * New inspections can't start until a version is active again; open ones keep theirs.
     */
    public function deactivate(PlatformUser $actor, InspectionTemplate $template): void
    {
        DB::transaction(function () use ($actor, $template) {
            $template = $this->lock($template);

            if (! $template->is_active) {
                throw ValidationException::withMessages(['template' => 'This version is not active.']);
            }

            $template->forceFill(['is_active' => false])->save();
            AuditLog::record($actor, null, 'inspection_template.deactivated', $template, ['version' => $template->version]);
        });
    }

    /**
     * Only a draft: published versions stay for the inspections that may use them.
     */
    public function deleteDraft(PlatformUser $actor, InspectionTemplate $template): void
    {
        DB::transaction(function () use ($actor, $template) {
            $template = $this->lock($template);

            if (! $template->isDraft()) {
                throw ValidationException::withMessages(['template' => 'Only a draft can be deleted. Deactivate a published version instead.']);
            }

            $template->delete();
            AuditLog::record($actor, null, 'inspection_template.deleted', $template, ['name' => $template->name, 'version' => $template->version]);
        });
    }

    private function lock(InspectionTemplate $template): InspectionTemplate
    {
        return InspectionTemplate::query()->lockForUpdate()->findOrFail($template->id);
    }

    private function lockEditable(InspectionTemplate $template): InspectionTemplate
    {
        $template = $this->lock($template);

        if ($template->isUsed()) {
            throw ValidationException::withMessages(['template' => 'This version has been used by an inspection. Create a new version to change it.']);
        }

        return $template;
    }

    /**
     * @return list<int> the ids deactivated
     */
    private function deactivateOthers(InspectionTemplate $template): array
    {
        $ids = InspectionTemplate::query()->where('inspection_type', $template->inspection_type)->where('is_active', true)
            ->whereKeyNot($template->id)->lockForUpdate()->pluck('id')->all();
        InspectionTemplate::query()->whereKey($ids)->update(['is_active' => false]);

        return $ids;
    }
}
