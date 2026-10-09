<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'module',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get vehicle documents of this type
     */
    public function vehicleDocuments()
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /**
     * Get trailer documents of this type
     */
    public function trailerDocuments()
    {
        return $this->hasMany(TrailerDocument::class);
    }

    /**
     * Get driver compliance documents of this type
     */
    public function driverComplianceDocuments()
    {
        return $this->hasMany(DriverComplianceDocument::class);
    }

    /**
     * Companies that have switched this type off for themselves
     */
    public function disabledByCompanies()
    {
        return $this->belongsToMany(Company::class, 'company_document_type')->withTimestamps();
    }

    /**
     * Ids of the types each company has switched off, keyed by company id.
     *
     * @param  list<int>|null  $companyIds  limit to these companies (null = all)
     * @return array<int, list<int>>
     */
    public static function disabledIdsByCompany(?array $companyIds = null): array
    {
        return DB::table('company_document_type')
            ->when($companyIds !== null, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->get(['company_id', 'document_type_id'])
            ->groupBy('company_id')
            ->map(fn ($rows) => $rows->pluck('document_type_id')->map(fn ($id) => (int) $id)->all())
            ->all();
    }

    /**
     * Scope to the types a company must comply with: active globally and not switched off by it.
     */
    public function scopeEnabledForCompany($query, ?int $companyId)
    {
        $query->where('status', true);

        if ($companyId) {
            $query->whereDoesntHave('disabledByCompanies', fn ($q) => $q->where('companies.id', $companyId));
        }

        return $query;
    }

    /**
     * Scope a query to only include active document types.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to get vehicle document types
     */
    public function scopeVehicle($query)
    {
        return $query->where('module', 'vehicle');
    }

    /**
     * Scope to get trailer document types
     */
    public function scopeTrailer($query)
    {
        return $query->where('module', 'trailer');
    }

    /**
     * Scope a query to filter by module.
     */
    public function scopeByModule($query, $module)
    {
        if ($module) {
            return $query->where('module', $module);
        }
        return $query;
    }

    /**
     * Get the available modules.
     */
    public static function getModules()
    {
        return [
            'driver' => 'Driver',
            'vehicle' => 'Vehicle',
            'trailer' => 'Trailer',
        ];
    }

    /**
     * Get module label.
     */
    public function getModuleLabelAttribute()
    {
        return self::getModules()[$this->module] ?? ucfirst($this->module);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }
}
