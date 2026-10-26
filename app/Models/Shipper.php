<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved shipper (08 §9, req 4.4). Inactive shippers are kept and stay on the trips that use
 * them, but pickers offer only active() ones.
 *
 * @property bool $is_active
 */
class Shipper extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory, SoftDeletes;

    protected const AUDIT_NAME = 'shipper';

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state_code',
        'zip',
        'country_code',
        'latitude',
        'longitude',
        'place_source',
        'place_ref',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Within the company only (12 §3: small sets, LIKE is enough).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)
            ->orWhere('city', 'like', $like)
            ->orWhere('contact_name', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('email', 'like', $like));
    }

    public function cityState(): string
    {
        return trim($this->city.', '.$this->state_code, ', ');
    }
}
