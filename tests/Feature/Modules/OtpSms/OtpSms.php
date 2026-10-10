<?php

namespace Tests\Feature\Modules\OtpSms;

use App\Models\OtpVerification;
use App\Services\OTPService;
use App\Services\PhoneNumberService;
use Mockery;
use Vonage\Client;
use Vonage\Verify\Client as VerifyClient;
use Vonage\Verify\Verification;

/**
 * Fixtures for the OTP/SMS services. Vonage is always mocked.
 */
final class OtpSms
{
    /**
     * An OTPService whose Vonage Verify client accepts start() and check() calls.
     */
    public static function service(?VerifyClient $verify = null): OTPService
    {
        if ($verify === null) {
            $verification = Mockery::mock(Verification::class);
            $verification->shouldReceive('getRequestId')->andReturnUsing(fn () => 'req-'.uniqid());

            $verify = Mockery::mock(VerifyClient::class);
            $verify->shouldReceive('start')->andReturn($verification);
        }

        $vonage = Mockery::mock(Client::class);
        $vonage->shouldReceive('verify')->andReturn($verify);

        return new OTPService($vonage, new PhoneNumberService());
    }

    public static function legacyOtp(string $phone, string $code, array $attributes = []): OtpVerification
    {
        return OtpVerification::create(array_merge([
            'phone' => $phone,
            'otp' => bcrypt($code),
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
            'method' => 'direct_sms',
        ], $attributes));
    }
}
