<?php

namespace App\Http\Requests\Platform;

use App\Support\Footer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FooterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        data_set($input, 'cta.enabled', $this->boolean('cta.enabled'));
        data_set($input, 'bottom.back_to_top', $this->boolean('bottom.back_to_top'));

        // Rows left blank are dropped, the rest renumbered
        $filled = fn ($rows, array $except = []) => array_values(array_filter(
            is_array($rows) ? $rows : [],
            fn ($row) => is_array($row) && collect($row)->except($except)->filter(fn ($v) => filled($v))->isNotEmpty(),
        ));

        $input['social'] = $filled($input['social'] ?? [], ['network']);
        $input['badges'] = $filled($input['badges'] ?? [], ['icon']);
        $input['columns'] = array_values(array_filter(array_map(function ($column) use ($filled) {
            if (! is_array($column)) {
                return null;
            }
            $column['links'] = $filled($column['links'] ?? [], ['target']);

            return filled($column['heading'] ?? null) || $column['links'] ? $column : null;
        }, is_array($input['columns'] ?? null) ? $input['columns'] : [])));

        $this->replace($input);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand.tagline' => ['nullable', 'string', 'max:300'],

            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.address' => ['nullable', 'string', 'max:300'],

            'social' => ['array', 'max:'.Footer::LIMITS['social']],
            'social.*.network' => ['required', Rule::in(array_keys(Footer::NETWORKS))],
            'social.*.url' => ['required', 'url:https', 'max:255'],

            'columns' => ['array', 'max:'.Footer::LIMITS['columns']],
            'columns.*.heading' => ['required', 'string', 'max:40'],
            'columns.*.links' => ['array', 'max:'.Footer::LIMITS['links']],
            'columns.*.links.*.label' => ['required', 'string', 'max:40'],
            'columns.*.links.*.target' => ['required', Rule::in(array_keys(Footer::targets()))],

            'badges' => ['array', 'max:'.Footer::LIMITS['badges']],
            'badges.*.icon' => ['required', Rule::in(Footer::BADGE_ICONS)],
            'badges.*.label' => ['required', 'string', 'max:60'],

            'cta.enabled' => ['boolean'],
            'cta.title' => ['required', 'string', 'max:120'],
            'cta.text' => ['nullable', 'string', 'max:300'],
            'cta.button_label' => ['required', 'string', 'max:40'],

            'bottom.copyright' => ['required', 'string', 'max:200'],
            'bottom.back_to_top' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'social.*.network' => 'network', 'social.*.url' => 'profile URL',
            'columns.*.heading' => 'column heading', 'columns.*.links.*.label' => 'link label', 'columns.*.links.*.target' => 'link destination',
            'badges.*.icon' => 'badge icon', 'badges.*.label' => 'badge text',
        ];
    }

    /**
     * Only the known keys, in Footer::defaults() shape.
     *
     * @return array<string, mixed>
     */
    public function footer(): array
    {
        $validated = $this->validated();

        $rows = fn (string $list, array $keys) => array_map(
            fn (array $row) => array_intersect_key($row, array_flip($keys)),
            $validated[$list] ?? [],
        );

        return [
            'brand' => ['tagline' => data_get($validated, 'brand.tagline')],
            'contact' => [
                'email' => data_get($validated, 'contact.email'),
                'phone' => data_get($validated, 'contact.phone'),
                'address' => data_get($validated, 'contact.address'),
            ],
            'social' => $rows('social', ['network', 'url']),
            'columns' => array_map(fn (array $column) => [
                'heading' => $column['heading'],
                'links' => array_map(fn (array $link) => ['label' => $link['label'], 'target' => $link['target']], $column['links'] ?? []),
            ], $validated['columns'] ?? []),
            'badges' => $rows('badges', ['icon', 'label']),
            'cta' => [
                'enabled' => (bool) data_get($validated, 'cta.enabled'),
                'title' => data_get($validated, 'cta.title'),
                'text' => data_get($validated, 'cta.text'),
                'button_label' => data_get($validated, 'cta.button_label'),
            ],
            'bottom' => [
                'copyright' => data_get($validated, 'bottom.copyright'),
                'back_to_top' => (bool) data_get($validated, 'bottom.back_to_top'),
            ],
        ];
    }
}
