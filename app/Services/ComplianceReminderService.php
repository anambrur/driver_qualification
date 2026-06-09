<?php

namespace App\Services;

use App\Mail\DriverComplianceReminderMail;
use App\Mail\VehicleComplianceStatusReminderMail;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ComplianceReminderService
{
    /**
     * Queue a driver compliance reminder email.
     *
     * @return array{success: bool, message: string}
     */
    public function sendDriverReminder(Driver $driver, DocumentType $documentType): array
    {
        if (empty($driver->email)) {
            return [
                'success' => false,
                'message' => 'Driver does not have an email address on file.',
            ];
        }

        $document = DriverComplianceDocument::query()
            ->where('driver_id', $driver->id)
            ->where('document_type_id', $documentType->id)
            ->first();

        $status = $this->resolveDocumentStatus($document);

        try {
            Mail::to($driver->email)->send(new DriverComplianceReminderMail(
                driver: $driver,
                documentType: $documentType,
                complianceStatus: $status['status'],
                statusLabel: $status['label'],
                expiryDate: $status['expiry_date'],
                daysUntilExpiry: $status['days_until_expiry'],
                companyName: $driver->company?->company_name,
            ));

            return [
                'success' => true,
                'message' => "Reminder email queued for {$driver->first_name} {$driver->last_name}.",
            ];
        } catch (Throwable $e) {
            Log::error('Failed to send driver compliance reminder email.', [
                'driver_id' => $driver->id,
                'document_type_id' => $documentType->id,
                'recipient' => $driver->email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send reminder email. Please try again later.',
            ];
        }
    }

    /**
     * Queue a vehicle or trailer compliance reminder email to the assigned driver.
     *
     * @return array{success: bool, message: string}
     */
    public function sendVehicleReminder(Model $asset, DocumentType $documentType, string $assetType): array
    {
        $asset->loadMissing(['assetGroups.driver.company', 'company']);

        if (! $asset->assetGroups || ! $asset->assetGroups->driver) {
            return [
                'success' => false,
                'message' => 'No driver assigned to this asset.',
            ];
        }

        $driver = $asset->assetGroups->driver;

        if (empty($driver->email)) {
            return [
                'success' => false,
                'message' => 'Assigned driver does not have an email address on file.',
            ];
        }

        $document = $this->findAssetDocument($asset, $documentType->id, $assetType);
        $status = $this->resolveDocumentStatus($document);

        try {
            Mail::to($driver->email)->send(new VehicleComplianceStatusReminderMail(
                driver: $driver,
                documentType: $documentType,
                assetType: $assetType,
                assetLabel: $this->resolveAssetLabel($asset),
                complianceStatus: $status['status'],
                statusLabel: $status['label'],
                expiryDate: $status['expiry_date'],
                daysUntilExpiry: $status['days_until_expiry'],
                companyName: $asset->company?->company_name ?? $driver->company?->company_name,
            ));

            return [
                'success' => true,
                'message' => "Reminder email queued for {$driver->first_name} {$driver->last_name}.",
            ];
        } catch (Throwable $e) {
            Log::error('Failed to send vehicle compliance reminder email.', [
                'asset_type' => $assetType,
                'asset_id' => $asset->id,
                'document_type_id' => $documentType->id,
                'recipient' => $driver->email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send reminder email. Please try again later.',
            ];
        }
    }

    /**
     * @return array{status: string, label: string, expiry_date: ?string, days_until_expiry: ?int}
     */
    private function resolveDocumentStatus(?Model $document): array
    {
        if (! $document) {
            return [
                'status' => 'missing',
                'label' => 'Missing',
                'expiry_date' => null,
                'days_until_expiry' => null,
            ];
        }

        $status = $document->status;

        return [
            'status' => $status,
            'label' => match ($status) {
                'expired' => 'Expired',
                'expiring' => 'Expiring Soon',
                default => 'Valid',
            },
            'expiry_date' => $document->expiry_date
                ? Carbon::parse($document->expiry_date)->format('F j, Y')
                : null,
            'days_until_expiry' => $document->days_until_expiry,
        ];
    }

    private function findAssetDocument(Model $asset, int $documentTypeId, string $assetType): ?Model
    {
        if ($assetType === 'vehicle') {
            return VehicleDocument::query()
                ->where('vehicle_id', $asset->id)
                ->where('document_type_id', $documentTypeId)
                ->first();
        }

        return TrailerDocument::query()
            ->where('trailer_id', $asset->id)
            ->where('document_type_id', $documentTypeId)
            ->first();
    }

    private function resolveAssetLabel(Model $asset): string
    {
        $unitNo = $asset->unit_no ?? 'N/A';
        $make = trim(($asset->year ? $asset->year.' ' : '').($asset->make ?? '').' '.($asset->model ?? ''));

        return trim($make) !== ''
            ? "Unit {$unitNo} ({$make})"
            : "Unit {$unitNo}";
    }
}
