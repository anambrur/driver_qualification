<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A platform-managed checklist version (08 §9, LKP-01). A pre-trip checklist, not a DVIR (C10).
 */
class InspectionTemplate extends Model
{
    use HasFactory;

    public const TYPES = ['pre_trip' => 'Pre-trip'];

    protected $fillable = ['name', 'inspection_type'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The published version new inspections of this type take.
     */
    public function scopeActiveOf(Builder $query, string $type): Builder
    {
        return $query->where('inspection_type', $type)->where('is_active', true)->whereNotNull('published_at');
    }

    /**
     * @return HasMany<InspectionTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InspectionTemplateItem::class, 'template_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * Used by any company's inspection; such a version is never edited (08 §9).
     */
    public function isUsed(): bool
    {
        return DB::table('inspections')->where('template_id', $this->id)->exists();
    }

    public function label(): string
    {
        return "{$this->name} v{$this->version}";
    }
}
