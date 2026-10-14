<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_price_id' => ['required', 'integer', Rule::exists('plan_prices', 'id')->whereNull('archived_at')],
            // Never open-ended (PO-19)
            'ends_at' => ['required', 'date', 'after:today'],
        ];
    }
}
