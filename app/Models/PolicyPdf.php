<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyPdf extends Model
{
    protected $fillable = [
        'alcohol_drug_test_policy_pdf',
        'general_work_policy_pdf',
    ];
}
