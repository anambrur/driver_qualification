<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
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

    /**
     * Global document types this company has switched off for itself.
     */
    public function disabledDocumentTypes()
    {
        return $this->belongsToMany(DocumentType::class, 'company_document_type')->withTimestamps();
    }
}
