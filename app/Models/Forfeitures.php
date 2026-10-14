<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forfeitures extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'denied_license' => 'boolean',
        'license_revoked' => 'boolean',
    ];
}
