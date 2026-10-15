<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new immutable price (07 §3). The Stripe Price is created in Stripe and its id entered here.
 */
class PlanPriceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper((string) $this->input('currency', 'USD'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'interval' => ['required', 'in:month,year'],
            'amount' => ['required', 'decimal:0,2', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'stripe_price_id' => ['nullable', 'string', 'max:255', 'starts_with:price_', 'unique:plan_prices,stripe_price_id'],
        ];
    }
}
