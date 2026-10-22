<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'provider' => 'google',
            'provider_user_id' => (string) fake()->unique()->numberBetween(100_000, 999_999_999),
            'provider_email' => fake()->unique()->safeEmail(),
        ];
    }
}
