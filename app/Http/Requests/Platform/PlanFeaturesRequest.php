<?php

namespace App\Http\Requests\Platform;

use App\Features\Feature;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The plan's feature matrix: features[key][enabled], features[key][limit_value] (blank = unlimited).
 */
class PlanFeaturesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['features' => ['required', 'array']];

        foreach (Feature::cases() as $feature) {
            $rules["features.{$feature->value}.enabled"] = ['nullable', 'boolean'];
            $rules["features.{$feature->value}.limit_value"] = $feature->isLimit() ? ['nullable', 'integer', 'min:0'] : ['prohibited'];
        }

        return $rules;
    }
}
