<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'iso_code',
        'phone_code',
        'currency_code',
        'currency_name',
    ];

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }
}
