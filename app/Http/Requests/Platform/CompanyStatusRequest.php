<?php

namespace App\Http\Requests\Platform;

use App\Services\Platform\PlatformCompanyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(PlatformCompanyService::STATUSES)],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
