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
            return $request->getCodeLength() === 6
                && $request->getPinExpiry() === 300;
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

    $record = OtpVerification::where('phone', $phone)->first();

    expect((int) now()->diffInSeconds($record->expires_at))->toBeGreaterThanOrEqual(295)
        ->and((int) now()->diffInSeconds($record->expires_at))->toBeLessThanOrEqual(300)
        ->and($result['expires_in'])->toBe(300);
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

test('verifyOTP rejects locally expired records before calling Vonage', function () {
    $phone = '+12092776341';
    $requestId = 'expired-request-id';

    $otpRecord = OtpVerification::create([
        'phone' => $phone,
        'otp' => 'vonage_verify',
        'expires_at' => now()->subSecond(),
        'is_used' => false,
        'method' => 'vonage_verify',
        'verification_sid' => $requestId,
    ]);

    $mockVerify = Mockery::mock(VerifyClient::class);
    $mockVerify->shouldNotReceive('check');

    $mockVonage = Mockery::mock(Client::class);
    $mockVonage->shouldReceive('verify')
        ->never();

    $phoneService = new PhoneNumberService();
    $service = new OTPService($mockVonage, $phoneService);

    $result = $service->verifyOTP($phone, '123456');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('OTP expired. Please request a new verification code.')
        ->and($otpRecord->fresh()->is_used)->toBeTrue();
});
