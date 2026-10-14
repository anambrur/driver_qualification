<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Webhook inbox row (07 §6.2): one per Stripe event id, processed by ProcessStripeEvent.
 *
 * @property string $event_id
 * @property string $type
 * @property Carbon|null $stripe_created_at
 * @property array<string, mixed> $payload
 * @property string $status
 */
class StripeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'type',
        'stripe_created_at',
        'payload',
        'status',
        'error',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'stripe_created_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function finish(string $status, ?string $error = null): void
    {
        $this->forceFill([
            'status' => $status,
            'error' => $error === null ? null : mb_substr($error, 0, 1000),
            'processed_at' => now(),
        ])->save();
    }
}
