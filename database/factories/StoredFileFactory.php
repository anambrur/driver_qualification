<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Only the row; no object is written to a disk. Keeps companies.storage_bytes in step,
 * as the real upload path must (integrity query 6).
 *
 * @extends Factory<StoredFile>
 */
class StoredFileFactory extends Factory
{
    protected $model = StoredFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hash = Str::lower(Str::random(40));

        return [
            'company_id' => CompanyFactory::new(),
            'disk' => 'local',
            'path' => fn (array $attributes) => sprintf('companies/%d/test/%s/%s.pdf', $attributes['company_id'], now()->format('Y/m'), $hash),
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 500_000),
            'checksum_sha256' => hash('sha256', $hash),
            'uploaded_by_user_id' => fn (array $attributes) => Company::find($attributes['company_id'])->owner_user_id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (StoredFile $file) => DB::table('companies')
            ->where('id', $file->company_id)
            ->increment('storage_bytes', $file->size_bytes));
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
