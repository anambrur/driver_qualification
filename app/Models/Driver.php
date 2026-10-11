<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    // Deleting hides the driver; the qualification file (child rows, documents, files) is kept.
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ssn' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<ResidenceAddress, $this>
     */
    public function residence_addresses(): HasMany
    {
        return $this->hasMany(ResidenceAddress::class);
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * @return HasMany<Experience, $this>
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    /**
     * @return HasMany<Accident, $this>
     */
    public function accidents(): HasMany
    {
        return $this->hasMany(Accident::class);
    }

    /**
     * @return HasMany<Violation, $this>
     */
    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    /**
     * @return HasMany<Forfeitures, $this>
     */
    public function forfeitures(): HasMany
    {
        return $this->hasMany(Forfeitures::class);
    }

    /**
     * @return HasMany<EmploymentRecord, $this>
     */
    public function employment_records(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class);
    }

    /**
     * @return HasOne<DriverDocument, $this>
     */
    public function driver_documents(): HasOne
    {
        return $this->hasOne(DriverDocument::class);
    }

    /**
     * @return HasMany<DriverComplianceDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverComplianceDocument::class);
    }
}
