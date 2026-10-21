<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory, SoftDeletes;

    protected const AUDIT_NAME = 'vehicle';

    protected $fillable = [
        'company_id',
        'unit_no',
        'vin',
        'year',
        'make',
        'model',
        'vehicle_type_id',
        'owned_by',
        'plate_number',
        'plate_state_code',
        'photo_file_id',
        'color',
        'title_no',
        'tire_size',
        'odometer',
        'engine_hours',
        'gvw',
        'vehicle_group_id',
        'fuel_type_id',
        'engine_type',
        'transmission',
        'suspension',
        'no_axles',
        'configuration',
        'wheel_base',
        'size_dimension',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'odometer' => 'integer',
        'engine_hours' => 'integer',
        'gvw' => 'integer',
        'no_axles' => 'integer',
        'wheel_base' => 'integer',
    ];

    // Relationships
    /**
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<VehicleGroup, $this>
     */
    public function vehicleGroup(): BelongsTo
    {
        return $this->belongsTo(VehicleGroup::class);
    }

    /**
     * @return BelongsTo<FuelType, $this>
     */
    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(FuelType::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'photo_file_id');
    }

    /**
     * @return HasOne<AssetGroup, $this>
     */
    public function assetGroups(): HasOne
    {
        return $this->hasOne(AssetGroup::class);
    }

    /**
     * @return HasMany<VehicleDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    // Scope for searching
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('unit_no', 'like', "%{$search}%")
                ->orWhere('vin', 'like', "%{$search}%")
                ->orWhere('make', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('plate_number', 'like', "%{$search}%")
                ->orWhere('year', 'like', "%{$search}%");
        });
    }

    // Accessor for full vehicle name
    public function getFullNameAttribute()
    {
        return "{$this->year} {$this->make} {$this->model} ({$this->unit_no})";
    }

    // Configuration options
    public static function getConfigurationOptions()
    {
        return [
            'conventional' => 'Conventional',
            'cabover' => 'Cabover',
        ];
    }

    // Owned by options
    public static function getOwnedByOptions()
    {
        return [
            'company' => 'Company Owned',
            'lease' => 'Leased',
            'rental' => 'Rental',
        ];
    }

    /**
     * Get compliance percentage
     */
    public function getCompliancePercentageAttribute()
    {
        $totalDocs = DocumentType::where('module', 'vehicle')
            ->enabledForCompany($this->company_id)
            ->count();

        if ($totalDocs === 0) {
            return 100;
        }

        $validDocs = $this->documents()
            ->whereHas('documentType', function ($query) {
                $query->enabledForCompany($this->company_id);
            })
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            })
            ->count();

        return round(($validDocs / $totalDocs) * 100, 1);
    }

    /**
     * Check if vehicle is compliant
     */
    public function isCompliant()
    {
        return $this->compliance_percentage >= 100;
    }

    /**
     * Get missing documents
     */
    public function getMissingDocumentsAttribute()
    {
        $requiredDocs = DocumentType::where('module', 'vehicle')
            ->enabledForCompany($this->company_id)
            ->get();

        $missing = [];

        foreach ($requiredDocs as $docType) {
            $hasDoc = $this->documents()
                ->where('document_type_id', $docType->id)
                ->where(function ($query) {
                    $query->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>=', now());
                })
                ->exists();

            if (! $hasDoc) {
                $missing[] = $docType->name;
            }
        }

        return $missing;
    }
}
