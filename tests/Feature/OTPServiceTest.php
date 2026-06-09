<?php

use App\Services\OTPService;
use App\Services\PhoneNumberService;
use App\Models\OtpVerification;
use Vonage\Client;
use Vonage\Verify\Client as VerifyClient;
use Vonage\Verify\Request as VerifyRequest;
use Vonage\Verify\Verification;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    OtpVerification::truncate();
    Cache::flush();
});

test('sendOTP starts verification via Vonage Verify and stores request ID', function () {
    $phone = '+8801700000000';
    $requestId = 'test-request-id-123';

    // Mock Verification response object
    $mockVerification = Mockery::mock(Verification::class);
    $mockVerification->shouldReceive('getRequestId')
        ->once()
        ->andReturn($requestId);

    // Mock Vonage Client
    $mockVerify = Mockery::mock(VerifyClient::class);
    $mockVerify->shouldReceive('start')
        ->once()
        ->with(Mockery::on(function (VerifyRequest $request) {
            return $request->getCodeLength() === 6;
        }))
        ->andReturn($mockVerification);

    $mockVonage = Mockery::mock(Client::class);
    $mockVonage->shouldReceive('verify')
        ->once()
        ->andReturn($mockVerify);

    $phoneService = new PhoneNumberService();
    $service = new OTPService($mockVonage, $phoneService);

    $result = $service->sendOTP($phone);

    expect($result['success'])->toBeTrue()
        ->and($result['verification_sid'])->toBe($requestId)
        ->and($result['method'])->toBe('vonage_verify');

    $this->assertDatabaseHas('otp_verifications', [
        'phone' => $phone,
        'verification_sid' => $requestId,
        'method' => 'vonage_verify',
        'is_used' => false,
    ]);
});

test('verifyOTP calls check on Vonage Verify client', function () {
    $phone = '+8801700000000';
    $requestId = 'test-request-id-123';
    $code = '123456';

    // Seed active verification
    $otpRecord = OtpVerification::create([
        'phone' => $phone,
        'otp' => 'vonage_verify',
        'expires_at' => now()->addMinutes(10),
        'is_used' => false,
        'method' => 'vonage_verify',
        'verification_sid' => $requestId,
    ]);

    // Mock Verification response object
    $mockVerification = Mockery::mock(Verification::class);

    // Mock Vonage Client
    $mockVerify = Mockery::mock(VerifyClient::class);
    $mockVerify->shouldReceive('check')
        ->once()
        ->with($requestId, $code)
        ->andReturn($mockVerification);

    $mockVonage = Mockery::mock(Client::class);
    $mockVonage->shouldReceive('verify')
        ->once()
        ->andReturn($mockVerify);

    $phoneService = new PhoneNumberService();
    $service = new OTPService($mockVonage, $phoneService);

    $result = $service->verifyOTP($phone, $code);

    expect($result['success'])->toBeTrue();
    expect($otpRecord->fresh()->is_used)->toBeTrue();
});
