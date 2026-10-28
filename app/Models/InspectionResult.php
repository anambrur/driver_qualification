<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer of an inspection (08 §9). Tenant detail: reached only through its inspection.
 */
class InspectionResult extends Model
{
    public const RESULTS = ['pass' => 'Pass', 'fail' => 'Fail', 'na' => 'N/A'];

    protected $fillable = ['template_item_id', 'item_label', 'result', 'note'];

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
