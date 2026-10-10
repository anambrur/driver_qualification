<?php

namespace App\Services\Stripe;

use Stripe\StripeClient;

class StripeClientFactory
{
    private static ?StripeClient $client = null;

    private static ?string $clientSecret = null;

    public function make(): StripeClient
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe secret key is not configured (services.stripe.secret).');
        }

        // Reuse the client only while the configured secret is unchanged (key rotation, tests, long-running workers).
        if (self::$client instanceof StripeClient && self::$clientSecret === $secret) {
            return self::$client;
        }

        self::$client = new StripeClient($secret);
        self::$clientSecret = $secret;

        return self::$client;
    }
}
