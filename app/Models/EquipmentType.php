<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    protected $fillable = ['name'];

    public function trailers()
    {
        return $this->hasMany(Trailer::class, 'equipment_types_id');
    }
}
