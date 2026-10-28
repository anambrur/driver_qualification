<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Inspection;
use App\Models\InspectionResult;
use App\Models\InspectionTemplate;
use App\Models\Trip;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Services\Documents\FolderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pre-trip inspections of a trip (req 5.1–5.3), recorded by staff for the driver until the app
 * (Phase 3). One open inspection per trip, on the active template version; answers are saved as
 * they are given; it completes once every required item is answered. A pre-trip checklist, not
 * a DVIR (C10). The trip row is locked before the inspection row, as TripLifecycleService does.
 */
class InspectionService
{
    public function __construct(private DocumentService $documents, private FolderService $folders) {}

    /**
     * The trip's open inspection, or a new one on the active version with the trip's driver,
     * vehicle and trailer.
     */
    public function start(Trip $trip, User $actor): Inspection
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);

            if ($trip->status !== 'assigned') {
                throw ValidationException::withMessages(['inspection' => 'Only an assigned trip can be inspected.']);
            }

            $open = $trip->inspections()->where('status', 'in_progress')->first();
            if ($open !== null) {
                return $open->matchesCrewOf($trip) ? $open : throw ValidationException::withMessages([
                    'inspection' => 'The open inspection was started for another driver or equipment. Abandon it, then start a new one.',
                ]);
            }

            // Shared lock: a platform item edit waits until this first use commits, then refuses
            $template = InspectionTemplate::query()->activeOf('pre_trip')->sharedLock()->first()
                ?? throw ValidationException::withMessages(['inspection' => 'No pre-trip checklist is active. Contact support.']);

            $inspection = new Inspection;
            $inspection->forceFill([
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
                'vehicle_id' => $trip->vehicle_id,
                'trailer_id' => $trip->trailer_id,
                'template_id' => $template->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'performed_by_user_id' => $actor->id,
            ])->save();

            return $inspection;
        });
    }

    /**
     * Saves the given answers ([template item id => ['result' => pass|fail|na, 'note' => ?string]])
     * and returns the inspection with its progress counts.
     *
     * @param  array<int, array{result: string, note?: string|null}>  $answers
     */
    public function saveResults(Inspection $inspection, array $answers, User $actor): Inspection
    {
        DB::transaction(function () use ($inspection, $answers, $actor) {
            $this->lockRecordableTrip($inspection);
            $inspection = $this->lockOpen($inspection);
            $items = $inspection->template->items()->whereKey(array_keys($answers))->pluck('label', 'id');

            if ($items->count() !== count($answers)) {
                throw ValidationException::withMessages(['results' => 'Answer only the items of this checklist.']);
            }

            $now = now();
            InspectionResult::query()->upsert(
                collect($answers)->map(fn (array $answer, int $itemId) => [
                    'inspection_id' => $inspection->id,
                    'template_item_id' => $itemId,
                    'item_label' => $items[$itemId],
                    'result' => $answer['result'],
                    'note' => ($answer['note'] ?? null) ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all(),
                ['inspection_id', 'template_item_id'],
                ['result', 'note', 'updated_at'],
            );

            if ($inspection->performed_by_user_id === null) {
                $inspection->forceFill(['performed_by_user_id' => $actor->id])->save();
            }
        });

        return $this->withProgress($inspection);
    }

    public function complete(Inspection $inspection, ?int $odometer, ?string $notes, User $actor): Inspection
    {
        return DB::transaction(function () use ($inspection, $odometer, $notes, $actor) {
            $trip = $this->lockRecordableTrip($inspection);
            $inspection = $this->lockOpen($inspection);

            if (! $inspection->matchesCrewOf($trip)) {
                throw ValidationException::withMessages(['inspection' => 'The trip\'s driver or equipment changed since this inspection started. Abandon it and start a new one.']);
            }

            $missing = $inspection->template->items()->where('is_required', true)
                ->whereNotIn('id', $inspection->results()->select('template_item_id'))->count();
            if ($missing > 0) {
                throw ValidationException::withMessages(['inspection' => $missing === 1 ? 'Answer the last required item first.' : "Answer every required item first: {$missing} left."]);
            }

            $failed = $inspection->results()->where('result', 'fail')->pluck('item_label')->all();
            $inspection->forceFill([
                'status' => 'completed',
                'result' => $failed === [] ? 'no_defects' : 'defects_found',
                'odometer' => $odometer,
                'notes' => $notes,
                'completed_at' => now(),
                'performed_by_user_id' => $actor->id,
            ])->save();
            AuditLog::record($actor, $inspection->company_id, 'inspection.completed', $inspection, [
                'trip_id' => $trip->id, 'result' => $inspection->result, 'failed' => $failed, 'odometer' => $odometer,
            ]);

            return $inspection;
        });
    }

    public function abandon(Inspection $inspection, User $actor): Inspection
    {
        return DB::transaction(function () use ($inspection, $actor) {
            $inspection = $this->lockOpen($inspection);
            $inspection->forceFill(['status' => 'abandoned'])->save();
            AuditLog::record($actor, $inspection->company_id, 'inspection.abandoned', $inspection, ['trip_id' => $inspection->trip_id]);

            return $inspection;
        });
    }

    /**
     * Photos or files for a failed item, filed in the trip folder with the inspection as
     * subject (F18); each document is titled "section · item". The checks and the upload share
     * one transaction, so the answer can't change or the inspection close in between.
     *
     * @param  list<UploadedFile>  $uploads
     * @return Collection<int, Document>
     */
    public function attach(Inspection $inspection, int $itemId, array $uploads, User $actor): Collection
    {
        return DB::transaction(function () use ($inspection, $itemId, $uploads, $actor) {
            $trip = $this->lockRecordableTrip($inspection);
            $inspection = $this->lockOpen($inspection);
            $label = $inspection->results()->where('template_item_id', $itemId)->where('result', 'fail')->value('item_label');

            if ($label === null) {
                throw ValidationException::withMessages(['files' => 'Mark the item as failed before adding photos or files.']);
            }

            $section = $inspection->template->items()->whereKey($itemId)->value('section');
            $folder = $trip->folder()->first() ?? $this->folders->ensureSystemFolder($trip);

            return $this->documents->upload($folder, $uploads, ['title' => mb_substr(self::attachmentTitle($section, $label), 0, 255)], $actor, ['inspection_id' => $inspection->id]);
        });
    }

    /**
     * How a failed item's files are titled, and found again on the inspection page.
     */
    public static function attachmentTitle(?string $section, string $label): string
    {
        return $section === null ? $label : "{$section} · {$label}";
    }

    /**
     * The inspection with results_count and items_count for progress().
     */
    public function withProgress(Inspection $inspection): Inspection
    {
        return $inspection->loadCount('results')->setAttribute(
            'items_count',
            DB::table('inspection_template_items')->where('template_id', $inspection->template_id)->count(),
        );
    }

    /**
     * The inspection's trip, locked; answers and files are recorded only while it waits to start.
     */
    private function lockRecordableTrip(Inspection $inspection): Trip
    {
        $trip = Trip::query()->lockForUpdate()->findOrFail($inspection->trip_id);

        if ($trip->status !== 'assigned') {
            throw ValidationException::withMessages(['inspection' => 'The trip is no longer waiting to start. Abandon this inspection.']);
        }

        return $trip;
    }

    private function lockOpen(Inspection $inspection): Inspection
    {
        $inspection = Inspection::query()->with('template')->lockForUpdate()->findOrFail($inspection->id);

        if (! $inspection->isOpen()) {
            throw ValidationException::withMessages(['inspection' => 'This inspection is already '.strtolower(Inspection::STATUSES[$inspection->status]).'.']);
        }

        return $inspection;
    }
}
