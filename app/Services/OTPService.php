<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Vonage\Client;
use Vonage\Verify\Request;

class OTPService
{
    protected int $otpExpiryMinutes = 5;

    protected int $maxAttempts = 3;

    protected int $resendTimeout = 60;

    public function __construct(
        private readonly Client $vonage,
        private readonly PhoneNumberService $phoneNumbers,
    ) {}

    /**
     * Send OTP to phone number.
     */
    public function sendOTP(string $phoneNumber): array
    {
        $phoneNumber = $this->phoneNumbers->normalize($phoneNumber);

        if (! $this->phoneNumbers->isValid($phoneNumber)) {
            return [
                'success' => false,
                'message' => 'Invalid phone number format.',
            ];
        }

        if ($this->hasRecentOTP($phoneNumber)) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting a new OTP',
            ];
        }

        if ($this->exceedsMaxAttempts($phoneNumber)) {
            return [
                'success' => false,
                'message' => 'Maximum OTP attempts reached. Please try again later.',
            ];
        }

        $this->invalidateExistingOTPs($phoneNumber);

        $appName = config('app.name', 'Driver Qualification');
        $brandName = substr($appName, 0, 15);
        if (empty($brandName)) {
            $brandName = 'DriverQual';
        }

        try {
            // Strip the + sign from normalized phone number for Vonage Verify
            $vonagePhone = ltrim($phoneNumber, '+');

            $verifyRequest = new Request($vonagePhone, $brandName);
            $verifyRequest->setCodeLength(6);
            $verifyRequest->setWorkflowId(Request::WORKFLOW_SMS);
            $verifyRequest->setPinExpiry($this->otpExpiryMinutes * 60);

            $response = $this->vonage->verify()->start($verifyRequest);
            $requestId = $response->getRequestId();

            if (!$requestId) {
                throw new \Exception('Failed to obtain verification request ID from Vonage.');
            }

            $otpRecord = OtpVerification::create([
                'phone' => $phoneNumber,
                'otp' => 'vonage_verify', // Dummy value as otp column is NOT NULL
                'expires_at' => now()->addMinutes($this->otpExpiryMinutes),
                'is_used' => false,
                'method' => 'vonage_verify',
                'verification_sid' => $requestId,
            ]);

            Cache::put(
                "otp:{$phoneNumber}:{$otpRecord->id}",
                [
                    'otp_id' => $otpRecord->id,
                    'phone' => $phoneNumber,
                    'method' => 'vonage_verify',
                ],
                now()->addMinutes($this->otpExpiryMinutes)
            );

            return [
                'success' => true,
                'message' => 'OTP sent successfully via Vonage Verify',
                'verification_sid' => $requestId,
                'otp_id' => $otpRecord->id,
                'method' => 'vonage_verify',
                'expires_in' => $this->otpExpiryMinutes * 60,
            ];
        } catch (\Throwable $e) {
            Log::error('OTP send failed via Vonage Verify', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send verification code. Please try again later.',
            ];
        }
    }

    /**
     * Verify OTP.
     */
    public function verifyOTP(string $phoneNumber, string $otpCode): array
    {
        $phoneNumber = $this->phoneNumbers->normalize($phoneNumber);
        $otpCode = trim($otpCode);

        if (! preg_match('/^\d{6}$/', $otpCode)) {
            return [
                'success' => false,
                'message' => 'Please enter a valid 6-digit verification code.',
            ];
        }

        $otpRecord = OtpVerification::forPhone($phoneNumber)
            ->where('is_used', false)
            ->orderByDesc('created_at')
            ->first();

        if (! $otpRecord) {
            return [
                'success' => false,
                'message' => 'Invalid verification code. Please request a new OTP if needed.',
            ];
        }

        if ($otpRecord->isExpired()) {
            $otpRecord->update(['is_used' => true]);

            return [
                'success' => false,
                'message' => 'OTP expired. Please request a new verification code.',
            ];
        }

        $otpRecord = OtpVerification::valid()
            ->forPhone($phoneNumber)
            ->where('is_used', false)
            ->orderByDesc('created_at')
            ->first();

        if (! $otpRecord) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ];
        }

        if ($otpRecord->method === 'vonage_verify') {
            try {
                $this->vonage->verify()->check($otpRecord->verification_sid, $otpCode);
            } catch (\Throwable $e) {
                Log::error('Vonage Verify check failed', [
                    'phone' => $phoneNumber,
                    'request_id' => $otpRecord->verification_sid,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification code.',
                ];
            }
        } else {
            // Fallback for legacy OTP if any exist in the database
            if (! Hash::check($otpCode, $otpRecord->otp)) {
                return [
                    'success' => false,
                    'message' => 'Invalid OTP code',
                ];
            }
        }

        $otpRecord->update([
            'is_used' => true,
        ]);

        Cache::forget("otp_attempts:{$phoneNumber}");

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
        ];
    }

    /**
     * Resend OTP.
     */
    public function resendOTP(string $phoneNumber): array
    {
        $phoneNumber = $this->phoneNumbers->normalize($phoneNumber);

        if ($this->hasRecentOTP($phoneNumber)) {
            $timeLeft = $this->resendTimeout - $this->getTimeSinceLastOTP($phoneNumber);

            return [
                'success' => false,
                'message' => "Please wait {$timeLeft} seconds before requesting a new OTP",
            ];
        }

        return $this->sendOTP($phoneNumber);
    }

    /**
     * Check OTP status.
     */
    public function checkOTPStatus(string $phoneNumber): array
    {
        $phoneNumber = $this->phoneNumbers->normalize($phoneNumber);

        $validOTP = OtpVerification::valid()
            ->forPhone($phoneNumber)
            ->where('is_used', false)
            ->exists();

        $recentAttempt = $this->hasRecentOTP($phoneNumber);
        $attemptsCount = $this->getAttemptsCount($phoneNumber);

        return [
            'has_valid_otp' => $validOTP,
            'can_resend' => ! $recentAttempt,
            'attempts_count' => $attemptsCount,
            'max_attempts' => $this->maxAttempts,
            'resend_timeout' => $this->resendTimeout,
        ];
    }

    /**
     * Validate phone number format.
     */
    public function validatePhoneNumber(string $phoneNumber): bool
    {
        return $this->phoneNumbers->isValid($phoneNumber);
    }

    /**
     * Get OTP expiry time.
     */
    public function getOtpExpiryTime(string $phoneNumber): ?array
    {
        $phoneNumber = $this->phoneNumbers->normalize($phoneNumber);

        $otp = OtpVerification::valid()
            ->forPhone($phoneNumber)
            ->where('is_used', false)
            ->orderByDesc('expires_at')
            ->first();

        if (! $otp) {
            return null;
        }

        return [
            'expires_at' => $otp->expires_at,
            'seconds_left' => now()->diffInSeconds($otp->expires_at, false),
        ];
    }

    /**
     * Clean up expired OTPs.
     */
    public function cleanupExpiredOTPs(): int
    {
        $deleted = OtpVerification::query()
            ->where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays(7))
            ->delete();

        return $deleted;
    }

    protected function generateOtpCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    protected function hasRecentOTP(string $phoneNumber): bool
    {
        return OtpVerification::forPhone($phoneNumber)
            ->where('created_at', '>', now()->subSeconds($this->resendTimeout))
            ->exists();
    }

    protected function getTimeSinceLastOTP(string $phoneNumber): int
    {
        $lastOTP = OtpVerification::forPhone($phoneNumber)
            ->orderByDesc('created_at')
            ->first();

        if (! $lastOTP) {
            return $this->resendTimeout + 1;
        }

        return now()->diffInSeconds($lastOTP->created_at);
    }

    protected function exceedsMaxAttempts(string $phoneNumber): bool
    {
        return $this->getAttemptsCount($phoneNumber) >= $this->maxAttempts;
    }

    protected function getAttemptsCount(string $phoneNumber): int
    {
        return OtpVerification::forPhone($phoneNumber)
            ->where('created_at', '>', now()->subHour())
            ->count();
    }

    protected function invalidateExistingOTPs(string $phoneNumber): int
    {
        return OtpVerification::forPhone($phoneNumber)
            ->where('is_used', false)
            ->update(['is_used' => true]);
    }
}
