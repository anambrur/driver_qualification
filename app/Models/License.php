<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $guarded = ['id', 'current_driver_id'];

    protected $casts = [
        'issued' => 'date',
        'expires' => 'date',
        'is_current' => 'boolean',
    ];
}
