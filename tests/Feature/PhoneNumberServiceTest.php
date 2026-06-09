<?php

use App\Services\PhoneNumberService;

test('normalizes valid us phone numbers to e164 without duplicating country code', function (string $input) {
    $service = new PhoneNumberService();

    expect($service->normalize($input))->toBe('+12092776341')
        ->and($service->isValid($input))->toBeTrue();
})->with([
    'plain national' => '2092776341',
    'formatted national' => '(209) 277-6341',
    'leading country code' => '12092776341',
    'formatted e164' => '+1 (209) 277-6341',
    'duplicate country code' => '+11 209 277 6341',
]);

test('rejects invalid us phone numbers', function (string $input) {
    $service = new PhoneNumberService();

    expect($service->isValid($input))->toBeFalse();
})->with([
    'area code starts with one' => '1234567890',
    'central office starts with one' => '2091776341',
    'too short' => '209277634',
    'too long duplicate invalid' => '+1112092776341',
]);

test('normalizes supported international phone numbers', function () {
    $service = new PhoneNumberService();

    expect($service->normalize('01700000000'))->toBe('+8801700000000')
        ->and($service->isValid('01700000000'))->toBeTrue()
        ->and($service->normalize('+91 98765 43210'))->toBe('+919876543210')
        ->and($service->isValid('+91 98765 43210'))->toBeTrue();
});
