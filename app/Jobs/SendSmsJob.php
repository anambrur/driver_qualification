<?php

namespace App\Jobs;

use App\Exceptions\Sms\SmsException;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $to,
        public readonly string $message,
        public readonly ?string $from = null,
    ) {}

    public function handle(SmsService $smsService): void
    {
        try {
            $smsService->send($this->to, $this->message, $this->from);
        } catch (SmsException $e) {
            Log::error('Queued SMS delivery failed', [
                'to' => $this->to,
                'provider_code' => $e->providerCode,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
