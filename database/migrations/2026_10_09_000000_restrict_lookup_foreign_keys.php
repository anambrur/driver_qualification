<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lookups (types, groups, categories, document types) are global master data. With
 * ON DELETE CASCADE, deleting one hard-deleted every tenant's rows that referenced it,
 * bypassing SoftDeletes. RESTRICT makes the database refuse instead.
 */
return new class extends Migration
{
    /**
     * Child table => list of [column, referenced lookup table].
     */
    private const LINKS = [
        'vehicles' => [
            ['vehicle_type_id', 'vehicle_types'],
            ['vehicle_group_id', 'vehicle_groups'],
            ['fuel_type_id', 'fuel_types'],
        ],
        'trailers' => [
            ['equipment_types_id', 'equipment_types'],
            ['vehicle_group_id', 'vehicle_groups'],
        ],
        'maintenance_schedules' => [['maintenance_category_id', 'maintenance_categories']],
        'service_log_category' => [['maintenance_category_id', 'maintenance_categories']],
        'driver_compliance_documents' => [['document_type_id', 'document_types']],
        'vehicle_documents' => [['document_type_id', 'document_types']],
        'trailer_documents' => [['document_type_id', 'document_types']],
    ];

    public function up(): void
    {
        $this->relink('restrict');
    }

    public function down(): void
    {
        $this->relink('cascade');
    }

    private function relink(string $onDelete): void
    {
        foreach (self::LINKS as $table => $links) {
            Schema::table($table, function (Blueprint $blueprint) use ($links, $onDelete) {
                foreach ($links as [$column, $lookupTable]) {
                    $blueprint->dropForeign([$column]);
                    $blueprint->foreign($column)->references('id')->on($lookupTable)->onDelete($onDelete);
                }
            });
        }
    }
};
