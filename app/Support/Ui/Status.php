<?php

namespace App\Support\Ui;

use Illuminate\Support\Str;

/**
 * One label and colour tone per status, for every badge in the app.
 */
final class Status
{
    private const TONES = [
        'active' => 'success',
        'approved' => 'success',
        'valid' => 'success',
        'paid' => 'success',
        'completed' => 'success',
        'pending' => 'warning',
        'under_review' => 'warning',
        'expiring' => 'warning',
        'on_leave' => 'warning',
        'past_due' => 'warning',
        'submitted' => 'info',
        'trialing' => 'info',
        'rejected' => 'danger',
        'expired' => 'danger',
        'failed' => 'danger',
        'canceled' => 'danger',
        'draft' => 'neutral',
        'inactive' => 'neutral',
        'terminated' => 'neutral',
    ];

    public static function tone(?string $status): string
    {
        return self::TONES[$status] ?? 'neutral';
    }

    public static function label(?string $status): string
    {
        return $status === null || $status === '' ? 'Unknown' : Str::headline($status);
    }
}
