<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidenceAddress extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_current' => 'boolean',
    ];
}
