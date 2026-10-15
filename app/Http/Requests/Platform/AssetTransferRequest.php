<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class AssetTransferRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_type' => ['required', 'in:vehicle,trailer'],
            'source_company_id' => ['required', 'integer', 'exists:companies,id'],
            'asset_id' => ['required', 'integer'],
            'target_company_id' => ['required', 'integer', 'exists:companies,id', 'different:source_company_id'],
            'reason' => ['required', 'string', 'max:1000'],
            // The operation can't be undone from the UI (04 §7: a confirmation step)
            'confirm' => ['accepted'],
        ];
    }
}
