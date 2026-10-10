<?php

namespace Tests\Feature\Modules\SubscriptionLifecycle;

use Mockery;
use Vonage\Client;
use Vonage\SMS\Client as SmsClient;
use Vonage\SMS\Message\SMS;

/**
 * Fixtures for the expiry-reminder command. Vonage is always mocked; nothing leaves the process.
 */
final class SubscriptionLifecycle
{
    /**
     * Bind a Vonage client whose sms()->send() records each SMS in $sent.
     * $failures is the number of leading send() calls that throw.
     *
     * @param  array<int, SMS>  $sent
     */
    public static function fakeVonage(array &$sent, int $failures = 0): void
    {
        config([
            'services.vonage.api_key' => 'key',
            'services.vonage.api_secret' => 'secret',
            'services.vonage.sms_from' => 'DFHub',
        ]);

        $sms = Mockery::mock(SmsClient::class);
        $sms->shouldReceive('send')->andReturnUsing(function (SMS $message) use (&$sent, &$failures) {
            if ($failures > 0) {
                $failures--;

                throw new \Vonage\Client\Exception\Exception('boom');
            }

            $sent[] = $message;

            return Mockery::mock(\Vonage\SMS\Collection::class);
        });

        $vonage = Mockery::mock(Client::class);
        $vonage->shouldReceive('sms')->andReturn($sms);

        app()->instance(Client::class, $vonage);
    }
}
