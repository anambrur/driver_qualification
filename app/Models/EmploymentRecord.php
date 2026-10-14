<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentRecord extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'employer_record_date_from' => 'date',
        'employer_record_date_to' => 'date',
        'employed_regulations' => 'boolean',
        'safety_sensitive_function' => 'boolean',
    ];
}
