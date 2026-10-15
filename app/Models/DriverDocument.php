<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDocument extends Model
{
    /** The wizard's file fields, each a {field}_file_id column → stored_files. */
    public const FILE_FIELDS = ['license_front', 'license_back', 'medical_card', 'forfeiture_document'];

    protected $guarded = ['id'];

    protected $casts = [
        'has_accidents' => 'boolean',
        'has_violations' => 'boolean',
    ];

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function licenseFrontFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'license_front_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function licenseBackFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'license_back_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function medicalCardFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'medical_card_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function forfeitureDocumentFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'forfeiture_document_file_id');
    }

    /**
     * The wizard views read each file field as its stored path (null when not uploaded).
     *
     * @return Attribute<?string, never>
     */
    protected function licenseFront(): Attribute
    {
        return Attribute::get(fn () => $this->licenseFrontFile?->path);
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function licenseBack(): Attribute
    {
        return Attribute::get(fn () => $this->licenseBackFile?->path);
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function medicalCard(): Attribute
    {
        return Attribute::get(fn () => $this->medicalCardFile?->path);
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function forfeitureDocument(): Attribute
    {
        return Attribute::get(fn () => $this->forfeitureDocumentFile?->path);
    }
}
