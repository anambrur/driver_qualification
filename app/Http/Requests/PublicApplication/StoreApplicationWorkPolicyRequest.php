<?php

namespace App\Http\Requests\PublicApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationWorkPolicyRequest extends FormRequest
{
    use ValidatesApplicationDriver;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => $this->applicationDriverRules(),
            'employee_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ];
    }
}
