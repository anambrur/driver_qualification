<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class Driver extends Model
{
    // Deleting hides the driver; the qualification file (child rows, documents, files) is kept.
    use AuditsDeletes, BelongsToCompany, SoftDeletes;

    protected const AUDIT_NAME = 'driver';

    public const MEDICAL_CERTIFICATE_KEY = 'medical_examiner_certificate';

    /** status() in SQL, for filters, counts and sorting. */
    public const STATUS_SQL = "CASE WHEN drivers.employment_status <> 'applicant' THEN drivers.employment_status ELSE COALESCE(drivers.application_status, 'pending') END";

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ssn' => 'encrypted',
            'date_of_birth' => 'date',
            'twic_card' => 'boolean',
            'passport' => 'boolean',
            'hazmat' => 'boolean',
            'lcv_certificate' => 'boolean',
            'overtime_exempt' => 'boolean',
            'withdrawn_at' => 'datetime',
            'rejected_at' => 'datetime',
            'hired_at' => 'datetime',
            'hire_date' => 'date',
            'terminated_at' => 'datetime',
            'termination_date' => 'date',
        ];
    }

    /**
     * The driver's own login, if invited.
     *
     * @return BelongsTo<User, $this>
     */
    public function login(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'photo_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
     * @return HasOne<License, $this>
     */
    public function currentLicense(): HasOne
    {
        return $this->hasOne(License::class)->where('is_current', true);
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

    /**
     * The medical examiner's certificate (F17): the oldest compliance document of that type.
     *
     * @return HasOne<DriverComplianceDocument, $this>
     */
    public function medicalCertificate(): HasOne
    {
        return $this->hasOne(DriverComplianceDocument::class)
            ->whereHas('documentType', fn (Builder $q) => $q->where('system_key', self::MEDICAL_CERTIFICATE_KEY))
            ->orderBy('id');
    }

    /**
     * medicalCertificate()'s expiry as a correlated subquery, for sorting lists.
     */
    public static function medicalCertificateExpirySubquery(): QueryBuilder
    {
        return DB::table('driver_compliance_documents as mc')
            ->join('document_types as mct', 'mct.id', '=', 'mc.document_type_id')
            ->whereColumn('mc.driver_id', 'drivers.id')
            ->where('mct.system_key', self::MEDICAL_CERTIFICATE_KEY)
            ->whereNull('mc.deleted_at')
            ->orderBy('mc.id')
            ->limit(1)
            ->select('mc.expiry_date');
    }

    /**
     * Drivers with any of the given display statuses (see status()).
     *
     * @param  Builder<Driver>  $query
     */
    public function scopeWithStatus(Builder $query, string ...$statuses): void
    {
        $query->whereIn(DB::raw(self::STATUS_SQL), $statuses);
    }

    /**
     * Drivers on the lists: public applications still in draft are not.
     *
     * @param  Builder<Driver>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('application_status')->orWhere('application_status', '!=', 'draft'));
    }

    /**
     * One status for lists and badges (F15): the employment status once employed, otherwise
     * the application stage (staff-created drivers have none and show as pending).
     *
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn () => $this->employment_status !== 'applicant'
            ? $this->employment_status
            : ($this->application_status ?? 'pending'));
    }

    /**
     * @return Attribute<?Carbon, never>
     */
    protected function medicalCertificateExpirationDate(): Attribute
    {
        return Attribute::get(fn () => $this->medicalCertificate?->expiry_date);
    }
}
