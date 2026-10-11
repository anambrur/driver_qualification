<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrailerDocument extends Model
{
    protected $fillable = [
        'trailer_id',
        'document_type_id',
        'expiry_date',
        'description',
        'file_path',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return BelongsTo<Trailer, $this>
     */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class);
    }

    /**
     * Check if document is expired
     */
    public function isExpired()
    {
        if (!$this->expiry_date) {
            return false;
        }

        return Carbon::parse($this->expiry_date)->isPast();
    }

    /**
     * Check if document is expiring soon (within 30 days)
     */
    public function isExpiringSoon()
    {
        if (!$this->expiry_date) {
            return false;
        }

        $expiryDate = Carbon::parse($this->expiry_date);
        $today = Carbon::today();

        return $expiryDate->isFuture() && $today->diffInDays($expiryDate) <= 30;
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute()
    {
        if (!$this->expiry_date) {
            return null;
        }

        return (int) Carbon::today()->diffInDays(Carbon::parse($this->expiry_date), false);
    }

    /**
     * Get document status
     */
    public function getStatusAttribute()
    {
        if (!$this->expiry_date) {
            return 'valid';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isExpiringSoon()) {
            return 'expiring';
        }

        return 'valid';
    }
}
