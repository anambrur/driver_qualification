<?php

namespace App\Services\Stripe;

use Stripe\StripeClient;

class StripeClientFactory
{
    private static ?StripeClient $client = null;

    public function make(): StripeClient
    {
        if (self::$client instanceof StripeClient) {
            return self::$client;
        }

        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe secret key is not configured (services.stripe.secret).');
        }

        self::$client = new StripeClient($secret);

        return self::$client;
    }
}
