<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'violation_date' => 'date',
    ];
}
