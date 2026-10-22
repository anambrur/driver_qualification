<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A folder (08 §8). A system folder (system_key set) is the standard folder of one driver
 * (DQ file), vehicle or trailer; custom folders nest below it, or at company level when unbound.
 * A child always has its parent's binding.
 */
class Folder extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory, SoftDeletes;

    protected const AUDIT_NAME = 'folder';

    public const MAX_DEPTH = 5;

    /** Binding column => system_key of the entity's standard folder. */
    public const BINDINGS = ['driver_id' => 'dq_file', 'vehicle_id' => 'vehicle', 'trailer_id' => 'trailer', 'trip_id' => 'trip'];

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'system_key',
        'driver_id',
        'vehicle_id',
        'trailer_id',
        'trip_id',
        'created_by_user_id',
    ];

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Trailer, $this>
     */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class)->withTrashed();
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /**
     * The stored name of an entity's standard folder: root names are unique per company and two
     * drivers may share a name, so it carries the id. Pages show label() instead.
     */
    public static function systemName(Driver|Vehicle|Trailer $entity): string
    {
        return match (true) {
            $entity instanceof Driver => "DQ file #{$entity->id}",
            $entity instanceof Vehicle => "Vehicle #{$entity->id}",
            default => "Trailer #{$entity->id}",
        };
    }

    public function label(): string
    {
        return match ($this->system_key) {
            'dq_file' => 'DQ file',
            'vehicle' => 'Vehicle documents',
            'trailer' => 'Trailer documents',
            default => $this->name,
        };
    }

    /**
     * The binding column and id, or null for a company-level folder.
     *
     * @return array{0: string, 1: int}|null
     */
    public function binding(): ?array
    {
        foreach (array_keys(self::BINDINGS) as $column) {
            if ($this->{$column} !== null) {
                return [$column, (int) $this->{$column}];
            }
        }

        return null;
    }

    /**
     * The binding as attributes for a child folder or a filed document (F18).
     *
     * @return array<string, int>
     */
    public function bindingAttributes(): array
    {
        $binding = $this->binding();

        return $binding === null ? [] : [$binding[0] => $binding[1]];
    }

    /**
     * The document_types module of the binding (driver, vehicle, trailer), null at company level.
     */
    public function module(): ?string
    {
        $binding = $this->binding();

        return $binding === null ? null : substr($binding[0], 0, -3);
    }

    /**
     * The folders of one binding, or the company-level folders when $binding is null.
     *
     * @param  Builder<Folder>  $query
     * @param  array{0: string, 1: int}|null  $binding
     */
    public function scopeBoundTo(Builder $query, ?array $binding): void
    {
        foreach (array_keys(self::BINDINGS) as $column) {
            $binding !== null && $binding[0] === $column
                ? $query->where($column, $binding[1])
                : $query->whereNull($column);
        }
    }
}
