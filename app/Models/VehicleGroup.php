<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleGroup extends Model
{
    protected $fillable = ['name'];

    public function trailers()
    {
        return $this->hasMany(Trailer::class);
    }


    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
