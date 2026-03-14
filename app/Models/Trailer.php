<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trailer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'unit_no',
        'vin',
        'year',
        'make',
        'model',
        'equipment_types_id',
        'owned_by',
        'color',
        'title_no',
        'tire_size',
        'gvw',
        'vehicle_group_id',
        'notes'
    ];

    protected $casts = [
        'year' => 'integer',
        'gvw' => 'integer'
    ];

    // Relationships
    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_types_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicleGroup()
    {
        return $this->belongsTo(VehicleGroup::class);
    }

    public function assetGroups()
    {
        return $this->hasOne(AssetGroup::class);
    }

    public function documents()
    {
        return $this->hasMany(TrailerDocument::class);
    }

    // Scope for searching
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('unit_no', 'like', "%{$search}%")
                ->orWhere('vin', 'like', "%{$search}%")
                ->orWhere('make', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('year', 'like', "%{$search}%");
        });
    }

    // Accessor for full trailer name
    public function getFullNameAttribute()
    {
        return "{$this->year} {$this->make} {$this->model} ({$this->unit_no})";
    }

    // Owned by options
    public static function getOwnedByOptions()
    {
        return [
            'company' => 'Company Owned',
            'lease' => 'Leased',
            'rental' => 'Rental'
        ];
    }

    /**
     * Get compliance percentage
     */
    public function getCompliancePercentageAttribute()
    {
        $totalDocs = DocumentType::where('module', 'trailer')
            ->where('status', true)
            ->count();

        if ($totalDocs === 0) {
            return 100;
        }

        $validDocs = $this->documents()
            ->whereHas('documentType', function ($query) {
                $query->where('status', true);
            })
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            })
            ->count();

        return round(($validDocs / $totalDocs) * 100, 1);
    }

    /**
     * Check if trailer is compliant
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
        $requiredDocs = DocumentType::where('module', 'trailer')
            ->where('status', true)
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

            if (!$hasDoc) {
                $missing[] = $docType->name;
            }
        }

        return $missing;
    }
}
