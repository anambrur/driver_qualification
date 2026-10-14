<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The client picks a current price; the amount always comes from plan_prices (never the client).
 */
class CheckoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_price_id' => ['required', 'integer', Rule::exists('plan_prices', 'id')->whereNull('archived_at')->whereNotNull('stripe_price_id')],
        ];
    }
}
