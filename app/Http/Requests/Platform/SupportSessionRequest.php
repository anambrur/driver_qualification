<?php

namespace App\Http\Requests\Platform;

use App\Models\PlatformSupportSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportSessionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'active')],
            'scope' => ['required', Rule::in(PlatformSupportSession::SCOPES)],
            // Shown to the company Owner, who decides on it
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
