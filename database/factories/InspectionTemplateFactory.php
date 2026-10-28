<?php

namespace Database\Factories;

use App\Models\InspectionTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionTemplate>
 */
class InspectionTemplateFactory extends Factory
{
    protected $model = InspectionTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'inspection_type' => 'pre_trip',
            'version' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true, 'published_at' => now()]);
    }

    /**
     * Items as [section, label, is_required] rows, in order.
     *
     * @param  list<array{0: string, 1: string, 2?: bool}>  $items
     */
    public function withItems(array $items = [['Lights', 'Headlights'], ['Lights', 'Brake lights'], ['Tires and wheels', 'Tread depth', false]]): static
    {
        return $this->afterCreating(function (InspectionTemplate $template) use ($items) {
            foreach ($items as $i => $item) {
                $template->items()->create(['section' => $item[0], 'label' => $item[1], 'is_required' => $item[2] ?? true, 'sort_order' => ($i + 1) * 10]);
            }
        });
    }
}
