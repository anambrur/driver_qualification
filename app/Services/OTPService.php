<?php
// app/Services/OTPService.php

namespace App\Services;

use Twilio\Rest\Client;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OTPService
{
    protected $twilio;
    protected $otpExpiryMinutes = 10;
    protected $maxAttempts = 3;
    protected $resendTimeout = 60; // seconds
    protected $verifyServiceSid;

    public function __construct()
    {
        $this->verifyServiceSid = config('services.twilio.verify_sid');
        
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->twilio = new Client($sid, $token);
    }

    /**
     * Send OTP to phone number
     */
    public function sendOTP($phoneNumber)
    {
        $phoneNumber = $this->cleanPhoneNumber($phoneNumber);

        // Check if recently sent
        if ($this->hasRecentOTP($phoneNumber)) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting a new OTP'
            ];
        }

        // Check max attempts
        if ($this->exceedsMaxAttempts($phoneNumber)) {
            return [
                'success' => false,
                'message' => 'Maximum OTP attempts reached. Please try again later.'
            ];
        }

        // Mark existing OTPs as used
        $this->invalidateExistingOTPs($phoneNumber);

        try {
            // Create OTP record with placeholder
            $otpRecord = OtpVerification::create([
                'phone' => $phoneNumber,
                'otp' => 'verify_api', // Placeholder - Twilio will generate actual OTP
                'expires_at' => now()->addMinutes($this->otpExpiryMinutes),
                'is_used' => false,
                'method' => 'verify_api',
                'verification_sid' => null
            ]);

            // Send via Twilio Verify API
            $verification = $this->twilio->verify->v2->services($this->verifyServiceSid)
                ->verifications
                ->create($phoneNumber, "sms");

            // Update record with verification SID
            $otpRecord->update(['verification_sid' => $verification->sid]);

            // Store in cache for quick lookup
            $cacheKey = "otp:{$phoneNumber}:{$verification->sid}";
            Cache::put($cacheKey, [
                'otp_id' => $otpRecord->id,
                'phone' => $phoneNumber,
                'method' => 'verify_api'
            ], now()->addMinutes($this->otpExpiryMinutes));

            return [
                'success' => true,
                'message' => 'OTP sent successfully via SMS',
                'verification_sid' => $verification->sid,
                'otp_id' => $otpRecord->id,
                'method' => 'verify_api',
                'expires_in' => $this->otpExpiryMinutes * 60
            ];
        } catch (\Exception $e) {
            Log::error('Verify API Error: ' . $e->getMessage(), [
                'phone' => $phoneNumber,
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $this->getErrorMessage($e->getCode()),
                'error_code' => $e->getCode()
            ];
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOTP($phoneNumber, $otpCode)
    {
        $phoneNumber = $this->cleanPhoneNumber($phoneNumber);

        // Find the latest valid OTP record
        $otpRecord = OtpVerification::valid()
            ->forPhone($phoneNumber)
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ];
        }

        try {
            $verificationCheck = $this->twilio->verify->v2->services($this->verifyServiceSid)
                ->verificationChecks
                ->create([
                    'to' => $phoneNumber,
                    'code' => $otpCode
                ]);

            if ($verificationCheck->status === 'approved') {
                // Mark as used and store the verified OTP
                $otpRecord->update([
                    'is_used' => true,
                    'otp' => $otpCode, // Store the actual OTP for audit
                    'verified_at' => now()
                ]);

                // Clear attempts cache
                Cache::forget("otp_attempts:{$phoneNumber}");

                return [
                    'success' => true,
                    'message' => 'OTP verified successfully'
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid OTP code'
            ];
        } catch (\Exception $e) {
            Log::error('Verify API Check Error: ' . $e->getMessage(), [
                'phone' => $phoneNumber,
                'error_code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'message' => 'OTP verification failed. Please try again.'
            ];
        }
    }

    /**
     * Resend OTP
     */
    public function resendOTP($phoneNumber)
    {
        $phoneNumber = $this->cleanPhoneNumber($phoneNumber);

        // Check resend timeout
        if ($this->hasRecentOTP($phoneNumber)) {
            $timeLeft = $this->resendTimeout - $this->getTimeSinceLastOTP($phoneNumber);
            return [
                'success' => false,
                'message' => "Please wait {$timeLeft} seconds before requesting a new OTP"
            ];
        }

        return $this->sendOTP($phoneNumber);
    }

    /**
     * Check OTP status
     */
    public function checkOTPStatus($phoneNumber)
    {
        $phoneNumber = $this->cleanPhoneNumber($phoneNumber);

        $validOTP = OtpVerification::valid()
            ->forPhone($phoneNumber)
            ->where('is_used', false)
            ->exists();

        $recentAttempt = $this->hasRecentOTP($phoneNumber);
        $attemptsCount = $this->getAttemptsCount($phoneNumber);

        return [
            'has_valid_otp' => $validOTP,
            'can_resend' => !$recentAttempt,
            'attempts_count' => $attemptsCount,
            'max_attempts' => $this->maxAttempts,
            'resend_timeout' => $this->resendTimeout
        ];
    }

    /**
     * Clean phone number for Twilio
     */
    protected function cleanPhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Handle Bangladesh local format (11 digits starting with 01)
        if (str_starts_with($cleaned, '01') && strlen($cleaned) == 11) {
            return '+880' . substr($cleaned, 1);
        }

        // Remove leading 0 if present
        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        // Format based on detected country
        if (str_starts_with($cleaned, '880') && strlen($cleaned) == 13) {
            // Bangladesh: +880XXXXXXXXXX
            return '+880' . substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '91') && strlen($cleaned) == 12) {
            // India: +91XXXXXXXXXX
            return '+91' . substr($cleaned, 2);
        } elseif (str_starts_with($cleaned, '1') && strlen($cleaned) == 11) {
            // US/Canada: +1XXXXXXXXXX
            return '+1' . substr($cleaned, 1);
        } elseif (strlen($cleaned) == 10) {
            // Assume US number
            return '+1' . $cleaned;
        }

        // Default: add + prefix
        return '+' . $cleaned;
    }

    /**
     * Check if recently sent OTP
     */
    protected function hasRecentOTP($phoneNumber)
    {
        return OtpVerification::forPhone($phoneNumber)
            ->where('created_at', '>', now()->subSeconds($this->resendTimeout))
            ->exists();
    }

    /**
     * Get time since last OTP
     */
    protected function getTimeSinceLastOTP($phoneNumber)
    {
        $lastOTP = OtpVerification::forPhone($phoneNumber)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastOTP) {
            return $this->resendTimeout + 1;
        }

        return now()->diffInSeconds($lastOTP->created_at);
    }

    /**
     * Check if exceeds max attempts
     */
    protected function exceedsMaxAttempts($phoneNumber)
    {
        $attempts = OtpVerification::forPhone($phoneNumber)
            ->where('created_at', '>', now()->subHour())
            ->count();

        return $attempts >= $this->maxAttempts;
    }

    /**
     * Get attempts count
     */
    protected function getAttemptsCount($phoneNumber)
    {
        return OtpVerification::forPhone($phoneNumber)
            ->where('created_at', '>', now()->subHour())
            ->count();
    }

    /**
     * Invalidate existing OTPs
     */
    protected function invalidateExistingOTPs($phoneNumber)
    {
        return OtpVerification::forPhone($phoneNumber)
            ->where('is_used', false)
            ->update(['is_used' => true]);
    }

    /**
     * Get error message from Twilio error code
     */
    protected function getErrorMessage($errorCode)
    {
        $errors = [
            21612 => 'Cannot send SMS to this country with current configuration.',
            21614 => 'Invalid phone number format.',
            21211 => 'Invalid phone number.',
            21408 => 'Permission to send SMS to this number is not enabled.',
            21608 => 'The message cannot be sent because the destination number is unreachable.',
            21610 => 'Message cannot be sent because the account is suspended.',
            20404 => 'The requested resource was not found.',
            20003 => 'Authentication error. Please check your Twilio credentials.',
            63038 => 'Daily SMS limit reached. Please try again tomorrow or contact support.',
        ];

        return $errors[$errorCode] ?? 'Failed to send OTP. Please try again or contact support.';
    }

    /**
     * Clean up expired OTPs
     */
    public function cleanupExpiredOTPs()
    {
        $deleted = OtpVerification::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays(7))
            ->delete();

        // Also clean cache
        Cache::flush();

        return $deleted;
    }

    /**
     * Validate phone number format
     */
    public function validatePhoneNumber($phoneNumber)
    {
        $cleaned = $this->cleanPhoneNumber($phoneNumber);

        // Validate international format
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $cleaned)) {
            return false;
        }

        // Validate length
        $length = strlen($cleaned);
        return $length >= 10 && $length <= 15;
    }

    /**
     * Get OTP expiry time
     */
    public function getOtpExpiryTime($phoneNumber)
    {
        $otp = OtpVerification::valid()
            ->forPhone($phoneNumber)
            ->where('is_used', false)
            ->orderBy('expires_at', 'desc')
            ->first();

        if (!$otp) {
            return null;
        }

        return [
            'expires_at' => $otp->expires_at,
            'seconds_left' => now()->diffInSeconds($otp->expires_at, false)
        ];
    }
}
