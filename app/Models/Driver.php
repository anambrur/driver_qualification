<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    // Deleting hides the driver; the qualification file (child rows, documents, files) is kept.
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ssn' => 'encrypted',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function residence_addresses()
    {
        return $this->hasMany(ResidenceAddress::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class);
    }

    public function accidents()
    {
        return $this->hasMany(Accident::class);
    }

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }

    public function forfeitures()
    {
        return $this->hasMany(Forfeitures::class);
    }

    public function employment_records()
    {
        return $this->hasMany(EmploymentRecord::class);
    }

    public function driver_documents()
    {
        return $this->hasOne(DriverDocument::class);
    }

    public function documents()
    {
        return $this->hasMany(DriverComplianceDocument::class);
    }
}
