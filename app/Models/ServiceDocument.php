<?php
// app/Models/ServiceDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDocument extends Model
{
    use HasFactory;

    protected $table = 'service_documents';

    protected $fillable = [
        'service_log_id',
        'original_name',
        'file_name',
        'file_path',
        'mime_type',
        'file_size'
    ];

    protected $casts = [
        'file_size' => 'integer'
    ];

    // Relationships
    public function serviceLog()
    {
        return $this->belongsTo(ServiceLog::class);
    }

    // Accessors
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    public function getFileIconAttribute()
    {
        $extension = pathinfo($this->original_name, PATHINFO_EXTENSION);

        $icons = [
            'pdf' => 'fa-file-pdf text-red-500',
            'doc' => 'fa-file-word text-blue-500',
            'docx' => 'fa-file-word text-blue-500',
            'xls' => 'fa-file-excel text-green-500',
            'xlsx' => 'fa-file-excel text-green-500',
            'jpg' => 'fa-file-image text-purple-500',
            'jpeg' => 'fa-file-image text-purple-500',
            'png' => 'fa-file-image text-purple-500',
            'txt' => 'fa-file-alt text-gray-500',
        ];

        return $icons[strtolower($extension)] ?? 'fa-file text-gray-500';
    }
}
