<?php

// app/Models/ServiceDocument.php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDocument extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'service_documents';

    protected $fillable = [
        'company_id',
        'service_log_id',
        'file_id',
    ];

    // Relationships
    /**
     * @return BelongsTo<ServiceLog, $this>
     */
    public function serviceLog(): BelongsTo
    {
        return $this->belongsTo(ServiceLog::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    // Accessors
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function getFileUrlAttribute()
    {
        // Files are on the private disk; only the authorized download route serves them
        return route('admin.service-log.download-document', $this->id);
    }

    public function getFileIconAttribute()
    {
        $extension = pathinfo($this->file->original_name, PATHINFO_EXTENSION);

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
