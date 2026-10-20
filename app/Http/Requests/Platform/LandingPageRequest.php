<?php

namespace App\Http\Requests\Platform;

use App\Support\Landing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LandingPageRequest extends FormRequest
{
    /** Sections with an on/off switch. */
    private const TOGGLED = ['stats', 'features', 'steps', 'pricing', 'testimonials', 'faq', 'cta'];

    /** Add/remove row lists. */
    private const LISTS = ['stats.items', 'features.items', 'steps.items'];

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach (self::TOGGLED as $section) {
            data_set($input, "{$section}.enabled", $this->boolean("{$section}.enabled"));
        }
        data_set($input, 'cta.demo', $this->boolean('cta.demo'));

        // Rows left blank are dropped, the rest renumbered
        foreach (self::LISTS as $list) {
            $rows = data_get($input, $list);
            data_set($input, $list, array_values(array_filter(
                is_array($rows) ? $rows : [],
                fn ($row) => is_array($row) && collect($row)->except(['icon', 'color'])->filter(fn ($v) => filled($v))->isNotEmpty(),
            )));
        }

        $input['hero']['bullets'] = array_values(array_filter((array) data_get($input, 'hero.bullets', []), fn ($v) => filled($v)));

        $this->replace($input);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $heading = fn (string $section) => [
            "{$section}.eyebrow" => ['nullable', 'string', 'max:40'],
            "{$section}.heading" => ['required', 'string', 'max:120'],
            "{$section}.highlight" => ['nullable', 'string', 'max:80'],
        ];

        return [
            'hero.badge' => ['nullable', 'string', 'max:60'],
            'hero.title' => ['required', 'string', 'max:120'],
            'hero.highlight' => ['nullable', 'string', 'max:80'],
            'hero.subtitle' => ['nullable', 'string', 'max:400'],
            'hero.bullets' => ['array', 'max:3'],
            'hero.bullets.*' => ['string', 'max:40'],

            'stats.enabled' => ['boolean'],
            'stats.items' => ['array', 'max:'.Landing::LIMITS['stats']],
            'stats.items.*.value' => ['required', 'string', 'max:20'],
            'stats.items.*.label' => ['required', 'string', 'max:60'],

            'features.enabled' => ['boolean'],
            ...$heading('features'),
            'features.subtitle' => ['nullable', 'string', 'max:400'],
            'features.items' => ['array', 'max:'.Landing::LIMITS['features']],
            'features.items.*.icon' => ['required', Rule::in(Landing::ICONS)],
            'features.items.*.color' => ['required', Rule::in(array_keys(Landing::COLORS))],
            'features.items.*.title' => ['required', 'string', 'max:80'],
            'features.items.*.text' => ['required', 'string', 'max:300'],

            'steps.enabled' => ['boolean'],
            ...$heading('steps'),
            'steps.subtitle' => ['nullable', 'string', 'max:400'],
            'steps.items' => ['array', 'max:'.Landing::LIMITS['steps']],
            'steps.items.*.icon' => ['required', Rule::in(Landing::ICONS)],
            'steps.items.*.title' => ['required', 'string', 'max:80'],
            'steps.items.*.text' => ['required', 'string', 'max:300'],

            'pricing.enabled' => ['boolean'],
            ...$heading('pricing'),
            'pricing.subtitle' => ['nullable', 'string', 'max:400'],

            'testimonials.enabled' => ['boolean'],
            ...$heading('testimonials'),

            'faq.enabled' => ['boolean'],
            ...$heading('faq'),

            'cta.enabled' => ['boolean'],
            'cta.title' => ['required', 'string', 'max:120'],
            'cta.text' => ['nullable', 'string', 'max:400'],
            'cta.demo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stats.items.*.value' => 'stat value', 'stats.items.*.label' => 'stat label',
            'features.items.*.icon' => 'feature icon', 'features.items.*.color' => 'feature colour',
            'features.items.*.title' => 'feature title', 'features.items.*.text' => 'feature text',
            'steps.items.*.icon' => 'step icon', 'steps.items.*.title' => 'step title', 'steps.items.*.text' => 'step text',
        ];
    }

    /**
     * Only the known keys of each section, in Landing::defaults() shape.
     *
     * @return array<string, array<string, mixed>>
     */
    public function landing(): array
    {
        $validated = $this->validated();

        return collect(Landing::defaults())
            ->map(fn (array $default, string $section) => collect($default)
                ->map(fn ($value, string $key) => data_get($validated, "{$section}.{$key}", is_array($value) ? [] : null))
                ->all())
            ->all();
    }
}
