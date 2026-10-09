<?php

namespace App\Http\Requests\PublicApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationPspRequest extends FormRequest
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
            'applicant_signature' => 'required|string|max:255',
            'authorization_agreement' => 'required|in:1',
            'date_signed' => 'required|date',
        ];
    }
}
