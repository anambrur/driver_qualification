<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'otp',
        'expires_at',
        'is_used',
        'method',
        'verification_sid'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    public function scopeValid($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    public function scopeForPhone($query, $phone)
    {
        return $query->where('phone', $phone);
    }

    // Scope for verification method
    public function scopeByMethod(Builder $query, $method)
    {
        return $query->where('method', $method);
    }

    // Prune old records automatically
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(7))
            ->orWhere(function ($query) {
                $query->where('expires_at', '<', now())
                    ->where('is_used', true);
            });
    }

    // Check if OTP is expired
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    // Check if OTP is valid
    public function isValid()
    {
        return !$this->is_used && !$this->isExpired();
    }

    // Mark as used
    public function markAsUsed()
    {
        $this->update([
            'is_used' => true,
            'verified_at' => now()
        ]);
    }
}
