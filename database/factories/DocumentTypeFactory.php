<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'module' => 'driver',
            'status' => true,
            'requires_expiry' => false,
        ];
    }

    /**
     * @param  'driver'|'vehicle'|'trailer'  $module
     */
    public function module(string $module): static
    {
        return $this->state(fn () => ['module' => $module]);
    }

    public function requiresExpiry(): static
    {
        return $this->state(fn () => ['requires_expiry' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}
