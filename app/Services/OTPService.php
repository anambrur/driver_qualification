<?php
// app/Services/OTPService.php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Cache;

class OTPService
{
    protected $twilio;
    protected $from;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->from = config('services.twilio.from');

        $this->twilio = new Client($sid, $token);
    }

    public function sendOTP($phoneNumber)
    {
        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Store OTP in cache for 10 minutes
        Cache::put("otp_$phoneNumber", $otp, now()->addMinutes(10));

        // Send SMS
        $message = "Your verification code is: $otp. Valid for 10 minutes.";

        try {
            $this->twilio->messages->create(
                $phoneNumber,
                [
                    'from' => $this->from,
                    'body' => $message
                ]
            );

            return ['success' => true, 'message' => 'OTP sent successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyOTP($phoneNumber, $otp)
    {
        $cachedOTP = Cache::get("otp_$phoneNumber");

        if (!$cachedOTP) {
            return ['success' => false, 'message' => 'OTP expired or not found'];
        }

        if ($cachedOTP == $otp) {
            // Clear OTP after successful verification
            Cache::forget("otp_$phoneNumber");
            return ['success' => true, 'message' => 'OTP verified successfully'];
        }

        return ['success' => false, 'message' => 'Invalid OTP'];
    }

    public function resendOTP($phoneNumber)
    {
        // Clear old OTP
        Cache::forget("otp_$phoneNumber");

        // Send new OTP
        return $this->sendOTP($phoneNumber);
    }
}
