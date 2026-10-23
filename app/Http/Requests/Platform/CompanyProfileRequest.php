<?php

namespace App\Http\Requests\Platform;

use App\Support\TimeZones;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Company metadata the platform may edit (04 §5). Status and ownership have their own actions;
 * the owner's login email belongs to the owner (D3b).
 */
class CompanyProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('state_code')) {
            $this->merge(['state_code' => strtoupper((string) $this->input('state_code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'dot_number' => ['nullable', 'string', 'max:20'],
            'mc_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state_code' => ['nullable', 'string', 'size:2', 'exists:states,code'],
            'zip' => ['nullable', 'string', 'max:20'],
            'timezone' => ['sometimes', 'required', 'string', TimeZones::rule()],
            'owner_name' => [$creating ? 'required' : 'prohibited', 'string', 'max:255'],
            'owner_email' => [$creating ? 'required' : 'prohibited', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->safe()->except(['owner_name', 'owner_email']);
    }
}
