<?php

namespace App\Console\Commands;

use App\Mail\DriverComplianceDigestMail;
use App\Models\DocumentType;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendComplianceDigestReminders extends Command
{
    protected $signature = 'compliance:send-digest-reminders';

    protected $description = 'Email active drivers a daily digest of missing, expired, or expiring compliance documents';

    public function handle(): int
    {
        $documentTypes = DocumentType::query()
            ->where('module', 'driver')
            ->where('status', true)
            ->get();

        if ($documentTypes->isEmpty()) {
            $this->info('No active driver document types configured.');

            return self::SUCCESS;
        }

        $sent = 0;

        Driver::query()
            ->with(['documents', 'company'])
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($drivers) use ($documentTypes, &$sent) {
                foreach ($drivers as $driver) {
                    $issues = $this->collectIssues($driver, $documentTypes);

                    if ($issues === []) {
                        continue;
                    }

                    $cacheKey = 'compliance_digest:driver:'.$driver->id.':'.now()->toDateString();
                    if (! Cache::add($cacheKey, true, now()->endOfDay())) {
                        continue;
                    }

                    try {
                        Mail::to($driver->email)->send(new DriverComplianceDigestMail(
                            driver: $driver,
                            issues: $issues,
                            companyName: $driver->company?->company_name,
                        ));
                        $sent++;
                    } catch (Throwable $e) {
                        Cache::forget($cacheKey);
                        Log::error('Failed to queue compliance digest email.', [
                            'driver_id' => $driver->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Compliance digests queued: {$sent}");

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, status: string, label: string, expiry_date: ?string}>
     */
    private function collectIssues(Driver $driver, $documentTypes): array
    {
        $issues = [];

        foreach ($documentTypes as $docType) {
            $document = $driver->documents->firstWhere('document_type_id', $docType->id);

            if (! $document) {
                $issues[] = [
                    'name' => $docType->name,
                    'status' => 'missing',
                    'label' => 'Missing',
                    'expiry_date' => null,
                ];

                continue;
            }

            if (! $document->expiry_date) {
                continue;
            }

            $expiryDate = Carbon::parse($document->expiry_date);
            $daysUntilExpiry = (int) Carbon::today()->diffInDays($expiryDate, false);

            if ($expiryDate->isPast()) {
                $issues[] = [
                    'name' => $docType->name,
                    'status' => 'expired',
                    'label' => 'Expired',
                    'expiry_date' => $expiryDate->format('F j, Y'),
                ];
            } elseif ($daysUntilExpiry <= 30) {
                $issues[] = [
                    'name' => $docType->name,
                    'status' => 'expiring',
                    'label' => 'Expiring Soon',
                    'expiry_date' => $expiryDate->format('F j, Y'),
                ];
            }
        }

        return $issues;
    }
}
