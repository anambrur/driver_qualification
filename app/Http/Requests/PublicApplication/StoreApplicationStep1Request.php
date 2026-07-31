<?php

namespace App\Http\Requests\PublicApplication;

use App\Http\Requests\Driver\StoreDriverRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationStep1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return StoreDriverRequest::step1FieldRules(true, true);
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Driver must be at least 18 years old.',
            'repeat_license_number.same' => 'License numbers do not match.',
            'license_expires.after' => 'License expiration date must be after the issued date.',
        ];
    }
}
