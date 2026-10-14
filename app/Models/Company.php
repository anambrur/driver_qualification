<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status_changed_at' => 'datetime',
        'trial_used_at' => 'datetime',
        'entitlements_version' => 'integer',
        'storage_bytes' => 'integer',
    ];

    /**
     * The billing account holder; always a user of this company.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasOne<CompanySetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Driver, $this>
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function trailers()
    {
        return $this->hasMany(Trailer::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Global document types this company has switched off for itself.
     */
    public function disabledDocumentTypes()
    {
        return $this->belongsToMany(DocumentType::class, 'company_document_type')->withTimestamps();
    }
}
