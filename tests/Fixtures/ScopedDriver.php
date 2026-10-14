<?php

namespace Tests\Fixtures;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant model on the drivers table, used to test BelongsToCompany before WP5 applies the
 * trait to the real models.
 */
class ScopedDriver extends Model
{
    use BelongsToCompany;

    protected $table = 'drivers';

    protected $guarded = [];
}
