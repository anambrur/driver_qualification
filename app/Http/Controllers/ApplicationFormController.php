<?php
// app/Http/Controllers/ApplicationFormController.php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Company;
use App\Services\OTPService;
use Illuminate\Http\Request;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApplicationFormController extends Controller
{
    protected $otpService;

    public function __construct(OTPService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Show the application form for a specific company
     *
     * @param  string  $slug
     * @return \Illuminate\View\View
     */
    public function show($slug)
    {
        // Find company by slug
        $company = Company::where('slug', $slug)
            ->where('status', 'active') // Only show for active companies
            ->firstOrFail();

        // Check if company allows applications
        if (!$company) {
            abort(404, 'This company is not currently accepting applications.');
        }

        return view('application.driver-application-form', compact('company'));
    }

    /**
     * Start a new application for a specific compa
     *
     * @param  string  $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function start($slug)
    {
        // Find company by slug
        $company = Company::where('slug', $slug)
            ->where('status', 'active') // Only allow for active companies
            ->firstOrFail();

        return view('application.application-start', compact('company'));
    }

    /**
     * Send OTP
     */
    public function sendOtp(Request $request, $slug)
    {
        // Find the company
        $company = Company::where('slug', $slug)->firstOrFail();

        // Validate request
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                function ($attribute, $value, $fail) use ($company) {
                    // Basic phone validation
                    $phone = preg_replace('/\D/', '', $value);

                    if (strlen($phone) < 10 || strlen($phone) > 15) {
                        $fail('Phone number must be 10-15 digits.');
                        return;
                    }

                    // Check if already registered
                    $exists = Driver::where('company_id', $company->id)
                        ->where('main_phone', $phone)
                        ->exists();

                    if ($exists) {
                        $fail('This phone number is already registered with our company.');
                    }
                }
            ],
            'confirm_phone' => 'required|same:phone',
        ], [
            'phone.required' => 'Phone number is required.',
            'confirm_phone.required' => 'Please confirm your phone number.',
            'confirm_phone.same' => 'Phone numbers do not match.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        // Format phone number
        $phone = $this->formatPhoneForTwilio($request->phone);

        // Validate phone format
        if (!$this->otpService->validatePhoneNumber($phone)) {
            toastr()->error('Invalid phone number format. Please enter a valid number.');
            return back()->withInput();
        }

        try {
            // Check OTP status
            $status = $this->otpService->checkOTPStatus($phone);

            if (!$status['can_resend']) {
                toastr()->error('Please wait before requesting a new OTP.');
                return back()->withInput();
            }

            if ($status['attempts_count'] >= $status['max_attempts']) {
                toastr()->error('Maximum OTP attempts reached. Please try again later.');
                return back()->withInput();
            }

            // Send OTP
            $result = $this->otpService->sendOTP($phone);

            if ($result['success']) {
                // Store in session
                session()->put([
                    'otp_verification_phone' => $phone,
                    'otp_company_slug' => $slug,
                    'otp_method' => $result['method'],
                    'otp_sent_at' => now()->timestamp
                ]);

                toastr()->success('OTP sent successfully!');
                return redirect()->route('application.verify.otp', $slug);
            } else {
                toastr()->error($result['message']);
                return back()->withInput();
            }
        } catch (\Exception $e) {
            Log::error('OTP Send Error: ' . $e->getMessage(), [
                'phone' => $phone,
                'company' => $company->id,
                'trace' => $e->getTraceAsString()
            ]);

            toastr()->error('An error occurred while sending OTP. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * Show OTP verification page
     */
    public function showVerifyOtp($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $phone = session()->get('otp_verification_phone');
        $method = session()->get('otp_method', 'direct_sms');

        if (!$phone) {
            toastr()->error('Please start the application process first.');
            return redirect()->route('application.start', $slug);
        }

        // Get OTP expiry info
        $expiryInfo = $this->otpService->getOtpExpiryTime($phone);

        return view('application.verify-otp', compact('company', 'phone', 'method', 'expiryInfo'));
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $phone = session()->get('otp_verification_phone');

        if (!$phone) {
            toastr()->error('Session expired. Please start again.');
            return redirect()->route('application.start', $slug);
        }

        // Validate OTP input
        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
            'digit1' => 'sometimes|digits:1',
            'digit2' => 'sometimes|digits:1',
            'digit3' => 'sometimes|digits:1',
            'digit4' => 'sometimes|digits:1',
            'digit5' => 'sometimes|digits:1',
            'digit6' => 'sometimes|digits:1',
        ]);

        if ($validator->fails()) {
            toastr()->error('Please enter a valid 6-digit OTP.');
            return back()->withInput();
        }

        // Get OTP from input
        $otp = $this->getOtpFromRequest($request);

        // Verify OTP
        $result = $this->otpService->verifyOTP($phone, $otp);

        if ($result['success']) {
            // Clear OTP session
            session()->forget([
                'otp_verification_phone',
                'otp_company_slug',
                'otp_method',
                'otp_sent_at'
            ]);

            // Store verified phone
            session()->put([
                'verified_phone' => $phone,
                'verified_company_slug' => $slug,
                'phone_verified_at' => now()->timestamp
            ]);

            toastr()->success('Phone number verified successfully!');
            return redirect()->route('application.form', $slug);
        } else {
            toastr()->error($result['message']);
            return back()->withInput();
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request, $slug)
    {
        $phone = $request->phone ?? session()->get('otp_verification_phone');

        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number not found.'
            ], 400);
        }

        $result = $this->otpService->resendOTP($phone);

        if ($result['success']) {
            // Update session
            session()->put([
                'otp_verification_phone' => $phone,
                'otp_method' => $result['method'],
                'otp_sent_at' => now()->timestamp
            ]);
        }

        return response()->json($result);
    }

    /**
     * Check OTP status (AJAX)
     */
    public function checkOtpStatus(Request $request)
    {
        $phone = $request->phone;

        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number required.'
            ], 400);
        }

        $status = $this->otpService->checkOTPStatus($phone);

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Format phone for Twilio
     */
    protected function formatPhoneForTwilio($phone)
    {
        // Remove all non-digits
        $cleaned = preg_replace('/\D/', '', $phone);

        // Remove leading 0
        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        // Add country code based on length
        if (strlen($cleaned) == 10) {
            // Assume US
            return '+1' . $cleaned;
        } elseif (str_starts_with($cleaned, '880') && strlen($cleaned) == 13) {
            // Bangladesh
            return '+880' . substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '91') && strlen($cleaned) == 12) {
            // India
            return '+91' . substr($cleaned, 2);
        }

        // If already has +, return as-is
        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        return '+' . $cleaned;
    }

    /**
     * Get OTP from request (handles both single field and digit-by-digit)
     */
    protected function getOtpFromRequest(Request $request)
    {
        if ($request->filled('otp')) {
            return $request->otp;
        }

        // Check for digit-by-digit input
        $digits = [
            $request->digit1,
            $request->digit2,
            $request->digit3,
            $request->digit4,
            $request->digit5,
            $request->digit6,
        ];

        // Filter out null values
        $digits = array_filter($digits, function ($digit) {
            return !is_null($digit) && $digit !== '';
        });

        if (count($digits) === 6) {
            return implode('', $digits);
        }

        return '';
    }
}
