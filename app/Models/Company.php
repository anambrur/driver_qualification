<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trailers()
    {
        return $this->hasMany(Trailer::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
