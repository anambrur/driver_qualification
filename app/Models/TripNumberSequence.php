<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * The company's next trip number (08 §9), read with SELECT … FOR UPDATE in the transaction that
 * inserts the trip. It only moves forward.
 *
 * @property int $next_number
 */
class TripNumberSequence extends Model
{
    use BelongsToCompany;

    protected $primaryKey = 'company_id';

    public $incrementing = false;

    protected $fillable = ['next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
