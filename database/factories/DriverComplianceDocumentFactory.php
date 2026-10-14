<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * DriverComplianceDocument has no HasFactory trait; use DriverComplianceDocumentFactory::new().
 *
 * @extends Factory<DriverComplianceDocument>
 */
class DriverComplianceDocumentFactory extends Factory
{
    use CreatesInTenant;

    protected $model = DriverComplianceDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => DriverFactory::new(),
            'company_id' => fn (array $attributes) => Driver::withoutTenancy()->withTrashed()->whereKey($attributes['driver_id'])->value('company_id'),
            'document_type_id' => DocumentTypeFactory::new(),
            'file_id' => fn (array $attributes) => StoredFileFactory::new()->create(['company_id' => $attributes['company_id']])->id,
            'file_date' => now()->subMonth()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ];
    }

    public function forDriver(Driver $driver, DocumentType $type): static
    {
        return $this->state(fn () => [
            'driver_id' => $driver->id,
            'company_id' => $driver->company_id,
            'document_type_id' => $type->id,
        ]);
    }
}
