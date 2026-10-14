<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accident extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'accident_date' => 'date',
        'number_of_injuries' => 'integer',
        'number_of_fatalities' => 'integer',
        'hazmat_spill' => 'boolean',
    ];
}
