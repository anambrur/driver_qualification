<?php

namespace App\Http\Requests\PublicApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationMedicalCardRequest extends FormRequest
{
    use ValidatesApplicationDriver;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The card and its expiry: DriverDocumentWizardService::medicalCardRules(), once the driver is known
            'driver_id' => $this->applicationDriverRules(),
        ];
    }
}
