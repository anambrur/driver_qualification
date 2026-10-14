<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'has_accidents' => 'boolean',
        'has_violations' => 'boolean',
    ];
}
