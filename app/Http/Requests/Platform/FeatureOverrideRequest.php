<?php

namespace App\Http\Requests\Platform;

use App\Features\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A platform exception for one company (05 §6): always with a reason, optionally with an end.
 */
class FeatureOverrideRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $revoking = $this->routeIs('platform.companies.overrides.revoke');

        return [
            'feature' => ['required', Rule::enum(Feature::class)],
            'reason' => ['required', 'string', 'max:255'],
            'enabled' => [$revoking ? 'prohibited' : 'nullable', 'boolean'],
            'limit_value' => [$revoking ? 'prohibited' : 'nullable', 'integer', 'min:0', 'prohibited_if:is_unlimited,1'],
            'is_unlimited' => [$revoking ? 'prohibited' : 'nullable', 'boolean'],
            'ends_at' => [$revoking ? 'prohibited' : 'nullable', 'date', 'after:now'],
        ];
    }
}
