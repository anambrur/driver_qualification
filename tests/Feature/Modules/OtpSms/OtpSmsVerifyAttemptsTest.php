<?php

use App\Models\OtpVerification;
use Tests\Feature\Modules\OtpSms\OtpSms;
use Vonage\Verify\Client as VerifyClient;

/*
| OTP-03: wrong codes were never counted, so the legacy hashed path could be brute-forced
| and a failed Vonage check left the record usable forever. After 3 wrong codes the record
| is now invalidated (OTP-04: this is what the otp_attempts:* cache key is for).
*/

test('legacy OTP is invalidated after 3 wrong codes', function () {
    $phone = '+12092776341';
    $otp = OtpSms::legacyOtp($phone, '123456');
    $service = OtpSms::service();

    foreach (['000000', '111111', '222222'] as $wrong) {
        expect($service->verifyOTP($phone, $wrong)['success'])->toBeFalse();
    }

    expect($service->verifyOTP($phone, '123456')['success'])->toBeFalse()
        ->and($otp->fresh()->is_used)->toBeTrue();
});

test('legacy OTP still verifies after fewer than 3 wrong codes', function () {
    $phone = '+12092776341';
    OtpSms::legacyOtp($phone, '123456');
    $service = OtpSms::service();

    $service->verifyOTP($phone, '000000');
    $service->verifyOTP($phone, '111111');

    expect($service->verifyOTP($phone, '123456')['success'])->toBeTrue();
});

test('Vonage OTP record is invalidated after 3 failed checks and Vonage is not called again', function () {
    $phone = '+12092776341';
    $otp = OtpVerification::create([
        'phone' => $phone, 'otp' => 'vonage_verify', 'method' => 'vonage_verify',
        'expires_at' => now()->addMinutes(5), 'is_used' => false, 'verification_sid' => 'req-1',
    ]);

    $verify = Mockery::mock(VerifyClient::class);
    $verify->shouldReceive('check')->times(3)->andThrow(new \RuntimeException('wrong code'));

    $service = OtpSms::service($verify);

    foreach (range(1, 4) as $i) {
        expect($service->verifyOTP($phone, '000000')['success'])->toBeFalse();
    }

    expect($otp->fresh()->is_used)->toBeTrue();
});

test('a new OTP resets the wrong-code counter', function () {
    $phone = '+12092776341';
    OtpSms::legacyOtp($phone, '123456')->forceFill(['created_at' => now()->subMinutes(2)])->save();
    $service = OtpSms::service();

    foreach (['000000', '111111', '222222'] as $wrong) {
        $service->verifyOTP($phone, $wrong);
    }

    expect($service->sendOTP($phone)['success'])->toBeTrue();

    expect(\Illuminate\Support\Facades\Cache::get("otp_attempts:{$phone}"))->toBeNull();
});
