<?php

namespace Tests\Support;

use App\Services\Stripe\StripeClientFactory;
use Mockery;
use Mockery\MockInterface;
use Stripe\StripeClient;

/**
 * Stripe helpers. Never let a test reach api.stripe.com: bind a mocked client and
 * sign webhook payloads locally.
 */
final class StripeFake
{
    public const WEBHOOK_SECRET = 'whsec_test_secret';

    /**
     * Build a valid Stripe-Signature header for $payload.
     */
    public static function signature(string $payload, string $secret = self::WEBHOOK_SECRET, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * JSON body for a Stripe event of $type wrapping $object.
     */
    public static function event(string $type, array $object, ?string $id = null): string
    {
        return json_encode([
            'id' => $id ?? 'evt_test_'.bin2hex(random_bytes(6)),
            'object' => 'event',
            'type' => $type,
            'api_version' => '2024-06-20',
            'created' => time(),
            'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Swap StripeClientFactory for one returning a Mockery StripeClient. Configure the
     * returned mock's services (e.g. $client->subscriptions = Mockery::mock(...)) per test.
     */
    public static function bindClient(): MockInterface
    {
        $client = Mockery::mock(StripeClient::class);

        $factory = Mockery::mock(StripeClientFactory::class);
        $factory->shouldReceive('make')->andReturn($client);

        app()->instance(StripeClientFactory::class, $factory);

        return $client;
    }
}
