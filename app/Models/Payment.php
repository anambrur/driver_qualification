<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'subscription_id', 'plan_id', 'invoice_number',
        'amount', 'currency', 'status', 'payment_method',
        'transaction_id', 'gateway_response', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Generate next invoice number: INV-2024-000001
     */
    public static function generateInvoiceNumber(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        return 'INV-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }
}
