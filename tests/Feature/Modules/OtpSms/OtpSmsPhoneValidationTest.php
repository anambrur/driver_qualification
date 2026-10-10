<?php

use App\Services\PhoneNumberService;
use Tests\Feature\Modules\OtpSms\OtpSms;

/*
| OTP-01: isValid() accepted any "+<country>" E.164 number, so an attacker could make the
| app send Verify SMS to premium-rate / IRSF destinations. Only the countries the service
| knows how to validate (+1 NANP, +880 BD, +91 IN) are accepted now, and NANP premium-rate
| area codes (900, 976) are refused.
*/

test('rejects numbers outside the supported country codes', function (string $phone) {
    expect((new PhoneNumberService())->isValid($phone))->toBeFalse();
})->with([
    'UK' => '+447700900123',
    'Latvia (IRSF hotspot)' => '+37120000000',
    'Somalia' => '+252612345678',
    'Sierra Leone' => '+23276123456',
]);

test('rejects NANP premium-rate area codes', function (string $phone) {
    expect((new PhoneNumberService())->isValid($phone))->toBeFalse();
})->with([
    '900' => '+19002345678',
    '976' => '9762345678',
    '900 with country code' => '19002345678',
]);

test('still accepts supported numbers', function (string $phone) {
    expect((new PhoneNumberService())->isValid($phone))->toBeTrue();
})->with([
    'US' => '+12092776341',
    'BD' => '+8801712345678',
    'IN' => '+919876543210',
]);

test('sendOTP never calls Vonage for an unsupported destination', function () {
    $verify = Mockery::mock(\Vonage\Verify\Client::class);
    $verify->shouldNotReceive('start');

    $result = OtpSms::service($verify)->sendOTP('+37120000000');

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseCount('otp_verifications', 0);
});
