<?php

// app/Models/ServiceDocument.php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDocument extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory;

    protected const AUDIT_NAME = 'service_document';

    protected $table = 'service_documents';

    protected $fillable = [
        'company_id',
        'service_log_id',
        'file_id',
    ];

    // The service log screens read the client name; the stored file stays out of the JSON
    protected $appends = ['original_name'];

    protected $hidden = ['file'];

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
    public function getOriginalNameAttribute(): ?string
    {
        return $this->file?->original_name;
    }

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
}
