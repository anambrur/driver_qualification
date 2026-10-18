<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default state is a paid monthly invoice.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'stripe_invoice_id' => 'in_test_'.fake()->unique()->bothify('????########'),
            'amount' => 49.00,
            'currency' => 'USD',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'paid_at' => now(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
