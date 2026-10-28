<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\FuelPurchase;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Services\Documents\FolderService;
use App\Services\Files\StoredFileService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Events recorded during a trip (08 §9, req 7.1–7.6), by staff for the driver until the app
 * (Phase 3): stops, police stops, weigh stations, accidents, breakdowns and fuel stops. Driver and
 * vehicle come from the trip. Files go into the trip folder with the event (or the fuel purchase)
 * as subject (F18). Evidence is never deleted, only voided. The trip row is locked first, as
 * TripLifecycleService does; one StoredFileService transaction removes written files on failure.
 */
class TripEventService
{
    /** A completed trip still takes events and files for this many days (late paperwork). */
    public const LATE_DAYS = 7;

    private const FUTURE_GRACE_MINUTES = 5;

    /** Upload slots: input name => [document type system_key, label]. "other" files are untyped. */
    public const SLOTS = [
        'ticket' => ['trip_police_ticket', 'Police ticket'],
        'weight' => ['trip_weight_ticket', 'Weight ticket'],
        'licence' => ['trip_driver_licence', 'Driver licence'],
        'insurance' => ['trip_insurance_card', 'Insurance card'],
        'photos' => ['trip_accident_photo', 'Accident photo'],
        'receipt' => ['trip_fuel_receipt', 'Fuel receipt'],
        'other' => [null, 'Other files'],
    ];

    /** The upload slots each event type offers. */
    public const TYPE_SLOTS = [
        'stop' => ['other'],
        'police_stop' => ['ticket', 'other'],
        'weigh_station' => ['weight', 'other'],
        'accident' => ['licence', 'insurance', 'photos', 'other'],
        'breakdown' => ['other'],
        'fuel' => ['receipt'],
    ];

    /** The fields each type keeps; the others are stored as null (the accident CHECKs). */
    private const TYPE_FIELDS = [
        'stop' => ['reason'],
        'police_stop' => ['ticket_issued', 'reason'],
        'weigh_station' => ['weight_ok', 'reason'],
        'accident' => ['injuries_count', 'fatalities_count', 'hazmat_released', 'reason'],
        'breakdown' => ['reason'],
    ];

    public function __construct(
        private StoredFileService $files,
        private DocumentService $documents,
        private FolderService $folders,
        private TripService $trips,
    ) {}

    /**
     * Events can be recorded while the trip is in progress, and for LATE_DAYS after it completed.
     */
    public static function isRecordable(Trip $trip): bool
    {
        return $trip->status === 'in_progress'
            || ($trip->status === 'completed' && $trip->completed_at?->gt(now()->subDays(self::LATE_DAYS)) === true);
    }

    /**
     * Any type but fuel (recordFuel). Required files (08 §9): a ticket when one was issued, and at
     * least one file for a failed weigh station and for an accident.
     *
     * @param  array<string, mixed>  $data  validated TripEventRequest data; occurred_at in the company time zone
     * @param  array<string, list<UploadedFile>>  $files  slot => uploads
     */
    public function record(Trip $trip, array $data, array $files, User $actor): TripEvent
    {
        $type = (string) $data['type'];
        if (! isset(self::TYPE_FIELDS[$type])) {
            throw ValidationException::withMessages(['type' => 'Choose a type of event.']);
        }
        $files = $this->filesForType($type, $files);
        $this->assertRequiredFiles($type, $data, $files);

        return $this->files->transaction(function () use ($trip, $data, $files, $type, $actor) {
            $trip = $this->lockRecordable($trip);
            $kept = array_intersect_key($data, array_flip(self::TYPE_FIELDS[$type]));

            $event = new TripEvent;
            $event->forceFill($this->baseAttributes($trip, $data, $actor) + array_fill_keys(['reason', 'ticket_issued', 'weight_ok', 'injuries_count', 'fatalities_count', 'hazmat_released'], null) + [
                'type' => $type,
            ])->forceFill(array_map(self::filledOrNull(...), $kept))->save();

            $documents = $this->attachFiles($trip, $event, $files, ['trip_event_id' => $event->id], $actor);
            AuditLog::record($actor, $trip->company_id, 'trip_event.recorded', $event, [
                'trip_id' => $trip->id, 'type' => $type, 'documents' => $documents,
            ]);

            return $event;
        });
    }

    /**
     * A fuel stop (req 7.6): the fuel event and its purchase in one transaction, written together
     * or not at all. Receipts confirm the purchase ("Done").
     *
     * @param  array<string, mixed>  $data  validated FuelStopRequest data
     * @param  list<UploadedFile>  $receipts
     */
    public function recordFuel(Trip $trip, array $data, array $receipts, User $actor): FuelPurchase
    {
        return $this->files->transaction(function () use ($trip, $data, $receipts, $actor) {
            $trip = $this->lockRecordable($trip);

            $event = new TripEvent;
            $event->forceFill($this->baseAttributes($trip, $data, $actor) + ['type' => 'fuel'])->save();

            $purchase = new FuelPurchase;
            $purchase->forceFill([
                'trip_id' => $trip->id,
                'trip_event_id' => $event->id,
                'driver_id' => $event->driver_id,
                'vehicle_id' => $event->vehicle_id,
                'fuel_type_id' => $data['fuel_type_id'] ?? null,
                'purchased_at' => $event->occurred_at,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'location_name' => ($data['location_name'] ?? null) ?: null,
                'state_code' => $event->state_code,
                'gallons' => $data['gallons'],
                'price_per_gallon' => $data['price_per_gallon'],
                'total_amount' => $data['total_amount'],
                'currency' => FuelPurchase::CURRENCY,
                'odometer' => $data['odometer'] ?? null,
                'recorded_by_user_id' => $actor->id,
            ])->save();

            AuditLog::record($actor, $trip->company_id, 'trip_event.recorded', $event, [
                'trip_id' => $trip->id, 'type' => 'fuel', 'fuel_purchase_id' => $purchase->id,
                'gallons' => (string) $purchase->gallons, 'total_amount' => (string) $purchase->total_amount,
            ]);
            if ($receipts !== []) {
                $this->attachReceipts($trip, $event, $purchase, $receipts, $actor);
            }

            return $purchase->setRelation('tripEvent', $event);
        });
    }

    /**
     * Files added after the event was recorded (late paperwork), into one of the type's slots.
     * The first receipt of a fuel stop confirms its purchase.
     *
     * @param  list<UploadedFile>  $uploads
     * @return int the number of files added
     */
    public function attach(TripEvent $event, string $slot, array $uploads, User $actor): int
    {
        if (! in_array($slot, self::TYPE_SLOTS[$event->type] ?? [], true)) {
            throw ValidationException::withMessages(['slot' => 'Choose what the files are.']);
        }

        return $this->files->transaction(function () use ($event, $slot, $uploads, $actor) {
            $trip = $this->lockRecordable(Trip::query()->findOrFail($event->trip_id));
            $event = $this->lockLive($event);

            if ($event->type === 'fuel') {
                $purchase = $event->fuelPurchase()->lockForUpdate()->firstOrFail();

                return $this->attachReceipts($trip, $event, $purchase, $uploads, $actor);
            }

            return $this->attachFiles($trip, $event, [$slot => $uploads], ['trip_event_id' => $event->id], $actor);
        });
    }

    /**
     * Evidence is never deleted (08 §9): the event is voided with a reason, its files stay. A voided
     * fuel event voids its purchase (FuelPurchase::live()).
     */
    public function void(TripEvent $event, string $reason, User $actor): TripEvent
    {
        return $this->files->transaction(function () use ($event, $reason, $actor) {
            $event = $this->lockLive($event);
            $event->forceFill(['voided_at' => now(), 'void_reason' => $reason])->save();

            AuditLog::record($actor, $event->company_id, 'trip_event.voided', $event, array_filter([
                'trip_id' => $event->trip_id, 'type' => $event->type, 'reason' => $reason,
                'fuel_purchase_id' => $event->type === 'fuel' ? $event->fuelPurchase()->value('id') : null,
            ]));

            return $event;
        });
    }

    /**
     * When an event happened by default, in the company time zone: now, or the completion of a
     * completed trip.
     */
    public static function defaultOccurredAt(Trip $trip, string $timezone): CarbonImmutable
    {
        $at = $trip->status === 'completed' && $trip->completed_at !== null ? CarbonImmutable::instance($trip->completed_at) : CarbonImmutable::now();

        return $at->setTimezone($timezone);
    }

    /**
     * Attributes shared by every type: the trip's driver and vehicle, the time and the place.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function baseAttributes(Trip $trip, array $data, User $actor): array
    {
        return [
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'vehicle_id' => $trip->vehicle_id,
            'occurred_at' => $this->occurredAt($trip, (string) $data['occurred_at']),
            'address' => self::filledOrNull($data['address'] ?? null),
            'city' => self::filledOrNull($data['city'] ?? null),
            'state_code' => self::filledOrNull($data['state_code'] ?? null),
            'latitude' => self::filledOrNull($data['latitude'] ?? null),
            'longitude' => self::filledOrNull($data['longitude'] ?? null),
            'recorded_by_user_id' => $actor->id,
        ];
    }

    private static function filledOrNull(mixed $value): mixed
    {
        return blank($value) ? null : $value;
    }

    /**
     * Typed in the company time zone (Z1), stored in UTC; between the start of the trip and now
     * (or its completion).
     */
    private function occurredAt(Trip $trip, string $value): CarbonImmutable
    {
        $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $this->trips->timezone())->utc();
        $earliest = CarbonImmutable::instance($trip->started_at ?? $trip->trip_date)->startOfMinute();
        $latest = $trip->status === 'completed' && $trip->completed_at !== null
            ? CarbonImmutable::instance($trip->completed_at)
            : CarbonImmutable::now()->addMinutes(self::FUTURE_GRACE_MINUTES);

        if ($at->lt($earliest) || $at->gt($latest)) {
            throw ValidationException::withMessages(['occurred_at' => $trip->status === 'completed'
                ? 'The time must be between the start and the completion of the trip.'
                : 'The time must be between the start of the trip and now.']);
        }

        return $at;
    }

    /**
     * Only the type's own slots, without empty ones.
     *
     * @param  array<string, list<UploadedFile>>  $files
     * @return array<string, list<UploadedFile>>
     */
    private function filesForType(string $type, array $files): array
    {
        return array_filter(array_intersect_key($files, array_flip(self::TYPE_SLOTS[$type])));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<UploadedFile>>  $files
     */
    private function assertRequiredFiles(string $type, array $data, array $files): void
    {
        $message = match (true) {
            $type === 'police_stop' && (bool) ($data['ticket_issued'] ?? false) && empty($files['ticket']) => ['files.ticket' => 'Upload the ticket.'],
            $type === 'weigh_station' && isset($data['weight_ok']) && ! $data['weight_ok'] && $files === [] => ['files' => 'Upload the weight ticket or other documents when the weight is not OK.'],
            $type === 'accident' && $files === [] => ['files' => 'Upload the documents of the accident: the driver licence, the insurance card or photos.'],
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages($message);
        }
    }

    private function lockRecordable(Trip $trip): Trip
    {
        $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);

        if (! self::isRecordable($trip)) {
            throw ValidationException::withMessages(['event' => $trip->status === 'completed'
                ? 'Events can be added up to '.self::LATE_DAYS.' days after the trip is completed.'
                : 'Events can be recorded once the trip is in progress.']);
        }

        return $trip;
    }

    private function lockLive(TripEvent $event): TripEvent
    {
        $event = TripEvent::query()->lockForUpdate()->findOrFail($event->id);

        if ($event->isVoided()) {
            throw ValidationException::withMessages(['event' => 'This event is voided.']);
        }

        return $event;
    }

    /**
     * Files into the trip folder, typed by slot and titled "Police ticket · Police stop Oct 28, 2026".
     *
     * @param  array<string, list<UploadedFile>>  $files
     * @param  array<string, int>  $subject
     * @return int the number of files
     */
    private function attachFiles(Trip $trip, TripEvent $event, array $files, array $subject, User $actor): int
    {
        if ($files === []) {
            return 0;
        }

        $folder = $trip->folder()->first() ?? $this->folders->ensureSystemFolder($trip);
        $when = $event->occurred_at->setTimezone($this->trips->timezone())->format('M d, Y');
        $count = 0;

        foreach ($files as $slot => $uploads) {
            [$key, $label] = self::SLOTS[$slot];
            $title = $slot === 'other' ? "{$event->label()} {$when}" : "{$label} · {$event->label()} {$when}";
            $typeId = $key === null ? null : DocumentType::query()->enabledForCompany($trip->company_id)->where('module', 'trip')->where('system_key', $key)->value('id');

            $count += $this->documents->upload($folder, array_values($uploads), ['title' => mb_substr($title, 0, 255), 'document_type_id' => $typeId], $actor, $subject)->count();
        }

        return $count;
    }

    /**
     * Receipts filed with the purchase as subject; the first one confirms it ("Done", req 7.6).
     *
     * @param  list<UploadedFile>  $receipts
     */
    private function attachReceipts(Trip $trip, TripEvent $event, FuelPurchase $purchase, array $receipts, User $actor): int
    {
        $count = $this->attachFiles($trip, $event, ['receipt' => $receipts], ['fuel_purchase_id' => $purchase->id], $actor);

        if ($purchase->confirmed_at === null) {
            $purchase->forceFill(['confirmed_at' => now()])->save();
            AuditLog::record($actor, $purchase->company_id, 'fuel_purchase.confirmed', $purchase, ['trip_id' => $trip->id, 'trip_event_id' => $event->id, 'documents' => $count]);
        }

        return $count;
    }
}
