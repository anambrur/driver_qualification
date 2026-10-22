<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Folder;
use App\Models\StoredFile;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'file_id' => fn (array $attributes) => StoredFileFactory::new()->state(['company_id' => $attributes['company_id']])->create()->id,
            'title' => fake()->words(3, true),
        ];
    }

    /**
     * Filed in $folder, with its binding as subject (F18).
     */
    public function inFolder(Folder $folder): static
    {
        return $this->state(fn () => ['company_id' => $folder->company_id, 'folder_id' => $folder->id]
            + ($folder->binding() === null ? [] : [$folder->binding()[0] => $folder->binding()[1]]));
    }

    public function withFile(StoredFile $file): static
    {
        return $this->state(fn () => ['file_id' => $file->id]);
    }
}
