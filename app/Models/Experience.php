<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'experience' => 'boolean',
        'from_date' => 'date',
        'to_date' => 'date',
        'approx_miles' => 'integer',
    ];
}
