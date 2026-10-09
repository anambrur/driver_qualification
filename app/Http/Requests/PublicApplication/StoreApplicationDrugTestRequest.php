<?php

namespace App\Http\Requests\PublicApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationDrugTestRequest extends FormRequest
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
            'drug_test_question_1' => 'required|in:yes,no',
            'drug_test_question_2' => 'required|in:yes,no,n/a',
            'applicant_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ];
    }
}
