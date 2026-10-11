<?php

use App\Services\PhoneNumberService;
use Tests\Feature\Modules\OtpSms\OtpSms;

/*
| OTP-01: isValid() accepted any "+<country>" E.164 number, so an attacker could make the
| app send Verify SMS to premium-rate / IRSF destinations. Only the countries the service
| knows how to validate (+1 NANP, +880 BD, +91 IN) are accepted now, and NANP premium-rate
| area codes (900, 976) are refused.
|
| Decided PO-11 (2026-10-11): production sends to the US and Canada only. Other NANP countries
| (Caribbean +1 codes) are refused; +880 / +91 are accepted on local and testing only.
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

test('still accepts US and Canadian numbers', function (string $phone) {
    expect((new PhoneNumberService())->isValid($phone))->toBeTrue();
})->with([
    'US' => '+12092776341',
    'US without +' => '2092776341',
    'Canada (Toronto)' => '+14165550123',
    'Canada (Vancouver)' => '6045550123',
]);

test('rejects non-US/Canada NANP countries (decided PO-11)', function (string $phone) {
    expect((new PhoneNumberService())->isValid($phone))->toBeFalse();
})->with([
    'Jamaica 876' => '+18765550123',
    'Jamaica 658' => '+16585550123',
    'Dominican Republic 809' => '+18095550123',
    'Dominican Republic 829' => '18295550123',
    'Dominican Republic 849' => '8495550123',
    'Bahamas' => '+12425550123',
    'Trinidad and Tobago' => '+18685550123',
    'Bermuda' => '+14415550123',
]);

test('rejects Bangladesh and India numbers in production (decided PO-11)', function (string $phone) {
    app()->detectEnvironment(fn () => 'production');

    expect((new PhoneNumberService())->isValid($phone))->toBeFalse();
})->with([
    'BD' => '+8801712345678',
    'BD national' => '01712345678',
    'IN' => '+919876543210',
    'IN without +' => '919876543210',
]);

test('accepts Bangladesh and India numbers for internal testing on local and testing', function (string $env, string $phone) {
    app()->detectEnvironment(fn () => $env);

    expect((new PhoneNumberService())->isValid($phone))->toBeTrue();
})->with(['local', 'testing'])->with([
    'BD' => '+8801712345678',
    'IN' => '+919876543210',
]);

test('sendOTP never calls Vonage for a Caribbean +1 number', function () {
    $verify = Mockery::mock(\Vonage\Verify\Client::class);
    $verify->shouldNotReceive('start');

    $result = OtpSms::service($verify)->sendOTP('+18765550123');

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseCount('otp_verifications', 0);
});

test('sendOTP never calls Vonage for an unsupported destination', function () {
    $verify = Mockery::mock(\Vonage\Verify\Client::class);
    $verify->shouldNotReceive('start');

    $result = OtpSms::service($verify)->sendOTP('+37120000000');

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseCount('otp_verifications', 0);
});
