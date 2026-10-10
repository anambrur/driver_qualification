<?php

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Modules\OtpSms\OtpSms;

/*
| OTP-02: the service only limited per phone (3/hour, 60 s resend). Per-IP limits exist on
| the routes (APP-04, guarded below); a global hourly cap is now enforced in sendOTP so
| rotating phones and IPs cannot pump unlimited SMS.
*/

test('public OTP-sending routes carry a per-IP throttle', function (string $name) {
    $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

    expect(collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:')))->toBeTrue();
})->with([
    'public.application.send.otp',
    'public.application.resend.otp',
    'public.application.submit.otp',
]);

test('sendOTP refuses once the global hourly cap is reached', function () {
    config(['services.vonage.otp_global_hourly_limit' => 3]);

    foreach (['+12092776341', '+12092776342', '+12092776343'] as $phone) {
        OtpVerification::create([
            'phone' => $phone, 'otp' => 'vonage_verify', 'method' => 'vonage_verify',
            'expires_at' => now()->addMinutes(5), 'is_used' => false, 'verification_sid' => 'x',
        ]);
    }

    $verify = Mockery::mock(\Vonage\Verify\Client::class);
    $verify->shouldNotReceive('start');

    $result = OtpSms::service($verify)->sendOTP('+12092776399');

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseMissing('otp_verifications', ['phone' => '+12092776399']);
});

test('sendOTP still works under the global cap', function () {
    config(['services.vonage.otp_global_hourly_limit' => 3]);

    expect(OtpSms::service()->sendOTP('+12092776399')['success'])->toBeTrue();
});

test('resendOTP reports the real remaining wait time', function () {
    $this->travelTo(now()->startOfMinute());
    $otp = OtpSms::legacyOtp('+12092776341', '123456');
    $otp->forceFill(['created_at' => now()->subSeconds(20)])->save();

    $result = OtpSms::service()->resendOTP('+12092776341');

    expect($result['message'])->toBe('Please wait 40 seconds before requesting a new OTP');
});
