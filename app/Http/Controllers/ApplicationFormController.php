<?php
// app/Http/Controllers/ApplicationFormController.php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Company;
use App\Models\Country;
use App\Models\State;
use App\Models\PolicyPdf;
use App\Models\Violation;
use App\Services\OTPService;
use Illuminate\Http\Request;
use App\Models\DriverDocument;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ApplicationFormController extends Controller
{
    protected $otpService;

    public function __construct(OTPService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Show the application landing page
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
     * Start application (Phone input)
     */
    public function start($slug)
    {
        $company = Company::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        return view('application.application-start', compact('company'));
    }

    /**
     * Send OTP
     */
    public function sendOtp(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                function ($attribute, $value, $fail) use ($company) {
                    $phone = preg_replace('/\D/', '', $value);

                    if (strlen($phone) < 10 || strlen($phone) > 15) {
                        $fail('Phone number must be 10-15 digits.');
                        return;
                    }

                    // Check if already registered as active
                    $exists = Driver::where('company_id', $company->id)
                        ->where('main_phone', $phone)
                        ->whereIn('status', ['active', 'pending'])
                        ->exists();

                    if ($exists) {
                        $fail('This phone number is already registered with our company.');
                    }
                }
            ],
            'confirm_phone' => 'required|same:phone',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $phone = $this->formatPhoneForTwilio($request->phone);

        if (!$this->otpService->validatePhoneNumber($phone)) {
            toastr()->error('Invalid phone number format. Please enter a valid number.');
            return back()->withInput();
        }

        try {
            $status = $this->otpService->checkOTPStatus($phone);

            if (!$status['can_resend']) {
                toastr()->error('Please wait before requesting a new OTP.');
                return back()->withInput();
            }

            if ($status['attempts_count'] >= $status['max_attempts']) {
                toastr()->error('Maximum OTP attempts reached. Please try again later.');
                return back()->withInput();
            }

            $result = $this->otpService->sendOTP($phone);

            if ($result['success']) {
                Session::put([
                    'otp_verification_phone' => $phone,
                    'otp_company_slug' => $slug,
                    'otp_method' => $result['method'],
                    'otp_sent_at' => now()->timestamp
                ]);

                toastr()->success('OTP sent successfully!');
                return redirect()->route('public.application.verify.otp', $slug);
            } else {
                toastr()->error($result['message']);
                return back()->withInput();
            }
        } catch (\Exception $e) {
            Log::error('OTP Send Error: ' . $e->getMessage());
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
        $phone = Session::get('otp_verification_phone');
        $method = Session::get('otp_method', 'direct_sms');

        if (!$phone) {
            toastr()->error('Please start the application process first.');
            return redirect()->route('public.application.start', $slug);
        }

        $expiryInfo = $this->otpService->getOtpExpiryTime($phone);

        return view('application.verify-otp', compact('company', 'phone', 'method', 'expiryInfo'));
    }

    /**
     * Verify OTP and create driver record
     */
    public function verifyOtp(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $phone = Session::get('otp_verification_phone');

        if (!$phone) {
            toastr()->error('Session expired. Please start again.');
            return redirect()->route('public.application.start', $slug);
        }

        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            toastr()->error('Please enter a valid 6-digit OTP.');
            return back()->withInput();
        }

        $result = $this->otpService->verifyOTP($phone, $request->otp);

        if ($result['success']) {
            Session::forget([
                'otp_verification_phone',
                'otp_company_slug',
                'otp_method',
                'otp_sent_at'
            ]);

            // Check if driver already exists in draft
            $driver = Driver::where('company_id', $company->id)
                ->where('main_phone', $phone)
                ->where('status', 'draft')
                ->where('source', 'public_application')
                ->first();

            if (!$driver) {
                // Create new driver record
                $driver = Driver::create([
                    'company_id' => $company->id,
                    'user_id' => $company->user_id,
                    'main_phone' => $phone,
                    'status' => 'draft',
                    'source' => 'public_application',
                ]);
            }

            // Store session data
            Session::put([
                'verified_phone' => $phone,
                'verified_company_slug' => $slug,
                'verified_company_id' => $company->id,
                'phone_verified_at' => now()->timestamp,
                'application_started' => true,
                'application_driver_id' => $driver->id,
                'current_step' => 1,
                'application_session_token' => md5($phone . $company->id . time())
            ]);

            toastr()->success('Phone number verified successfully!');
            return redirect()->route('public.application.step1', $slug);
        } else {
            toastr()->error($result['message']);
            return back()->withInput();
        }
    }

    /**
     * Resume application
     */
    public function resume($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        return view('application.resume', compact('company'));
    }

    public function verifyResume(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'date_of_birth' => 'required|date',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $phone = $this->formatPhoneForTwilio($request->phone);
        $company = Company::where('slug', $slug)->firstOrFail();

        // Find driver
        $driver = Driver::where('company_id', $company->id)
            ->where('main_phone', $phone)
            ->where('date_of_birth', $request->date_of_birth)
            ->where('source', 'public_application')
            ->whereIn('status', ['draft', 'pending'])
            ->first();

        if ($driver) {
            Session::put([
                'verified_phone' => $phone,
                'verified_company_slug' => $slug,
                'verified_company_id' => $company->id,
                'application_started' => true,
                'application_driver_id' => $driver->id,
                'current_step' => $this->calculateCurrentStep($driver),
                'application_session_token' => md5($phone . $company->id . time())
            ]);

            toastr()->success('Application found! Redirecting to where you left off...');
            return redirect()->route('public.application.step' . $this->calculateCurrentStep($driver), [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } else {
            toastr()->error('No application found with those details.');
            return back()->withInput();
        }
    }

    /**
     * STEP 1: Basic Information
     */
    public function step1($slug)
    {
        $this->checkApplicationSession($slug);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driverId = Session::get('application_driver_id');
        $driver = Driver::findOrFail($driverId);
        $countries = Country::orderBy('name')->get();
        $defaultCountry = Country::where('iso_code', 'US')->first();
        $states = $defaultCountry ? $defaultCountry->states()->orderBy('name')->get() : collect();
        $currentStep = 1;

        return view('application.steps.step1-basic-info', compact(
            'company',
            'driver',
            'countries',
            'states',
            'defaultCountry',
            'currentStep'
        ));
    }

    public function storeStep1(Request $request, $slug)
    {
        $this->checkApplicationSession($slug);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'date_of_birth' => 'required|date|before:-18 years',
            'ssn' => 'required|string|max:11',
            'main_phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'medical_certificate_expiration_date' => 'required|date|after_or_equal:' . now()->format('Y-m-d'),
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'business_name' => 'nullable|string|max:255',
            'employer_identification_number' => 'nullable|string|max:20',
            'federal_tax_classification' => 'nullable|in:individual_sole_proprietor,c_corporation,s_corporation,llc',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => 'required',
            'country' => 'required',
            'postal_code' => 'required|string|max:20',
            'twic_card' => 'sometimes|boolean',
            'passport' => 'sometimes|boolean',

            // License validation
            'license_first_name' => 'required|string|max:255',
            'license_last_name' => 'required|string|max:255',
            'license_issued' => 'required|date',
            'license_expires' => 'required|date|after:license_issued',
            'license_country' => 'required',
            'license_state' => 'required',
            'license_class' => 'required|string|max:255',
            'license_number' => 'required|string|max:255',
            'repeat_license_number' => 'required|same:license_number',
            'is_h_placarded_hazmat' => 'sometimes|boolean',
            'is_n_tank_vehicle' => 'sometimes|boolean',
            'is_p_passengers' => 'sometimes|boolean',
            'is_t_double_trailer' => 'sometimes|boolean',
            'is_s_school_bus' => 'sometimes|boolean',
            'is_x_placarded_hazmat' => 'sometimes|boolean',

            // Array validations - make more flexible
            'residence_address' => 'sometimes|array',
            'residence_address.*' => 'nullable|string',
            'residence_city' => 'sometimes|array',
            'residence_city.*' => 'nullable|string',
            'residence_country' => 'sometimes|array',
            'residence_country.*' => 'nullable',
            'residence_state' => 'sometimes|array',
            'residence_state.*' => 'nullable',
            'residence_postal_code' => 'sometimes|array',
            'residence_postal_code.*' => 'nullable|string',

            'equipment_class' => 'required|array',
            'equipment_class.*' => 'required|string',
            'experience' => 'required|array',
            'experience.*' => 'required|in:no,yes',
            'experience_from_date' => 'sometimes|array',
            'experience_from_date.*' => 'nullable|date',
            'experience_to_date' => 'sometimes|array',
            'experience_to_date.*' => 'nullable|date',
            'approx_miles' => 'sometimes|array',
            'approx_miles.*' => 'nullable|string',

            'accident' => 'required|in:no,yes',
            'accident_date' => 'sometimes|array',
            'accident_date.*' => 'nullable|date',
            'accident_location' => 'sometimes|array',
            'accident_location.*' => 'nullable|string',
            'number_of_injuries' => 'sometimes|array',
            'number_of_injuries.*' => 'nullable|string',
            'number_of_fatalities' => 'sometimes|array',
            'number_of_fatalities.*' => 'nullable|string',
            'hazmat_spill' => 'sometimes|array',
            'hazmat_spill.*' => 'nullable|in:no,yes',

            'violation' => 'required|in:no,yes',
            'violation_date' => 'sometimes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'sometimes|array',
            'violation_location.*' => 'nullable|string',
            'offense' => 'sometimes|array',
            'offense.*' => 'nullable|string',
            'vehicle_type' => 'sometimes|array',
            'vehicle_type.*' => 'nullable|string',

            'denied_license' => 'required|in:no,yes',
            'license_revoked' => 'required|in:no,yes',
            'forfeitures' => 'nullable|string|max:1000',

            'employer_name' => 'sometimes|array',
            'employer_name.*' => 'nullable|string',
            'employer_record_address' => 'sometimes|array',
            'employer_record_address.*' => 'nullable|string',
            'employer_record_city' => 'sometimes|array',
            'employer_record_city.*' => 'nullable|string',
            'employer_record_country' => 'sometimes|array',
            'employer_record_country.*' => 'nullable',
            'employer_record_state' => 'sometimes|array',
            'employer_record_state.*' => 'nullable',
            'employer_record_postal_code' => 'sometimes|array',
            'employer_record_postal_code.*' => 'nullable|string',
            'employer_record_phone' => 'sometimes|array',
            'employer_record_phone.*' => 'nullable|string',
            'employer_record_fax' => 'sometimes|array',
            'employer_record_fax.*' => 'nullable|string',
            'employer_record_email' => 'sometimes|array',
            'employer_record_email.*' => 'nullable|email',
            'employer_record_position' => 'sometimes|array',
            'employer_record_position.*' => 'nullable|string',
            'employer_record_date_from' => 'sometimes|array',
            'employer_record_date_from.*' => 'nullable|date',
            'employer_record_date_to' => 'sometimes|array',
            'employer_record_date_to.*' => 'nullable|date',
            'employer_record_reason_for_leaving' => 'sometimes|array',
            'employer_record_reason_for_leaving.*' => 'nullable|string',
            'employed_regulations' => 'sometimes|array',
            'employed_regulations.*' => 'nullable|in:no,yes',
            'safety_sensitive_function' => 'sometimes|array',
            'safety_sensitive_function.*' => 'nullable|in:no,yes',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        DB::beginTransaction();
        try {
            $driverId = Session::get('application_driver_id');
            $driver = Driver::findOrFail($driverId);

            // Handle photo upload
            $photo = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $fileName = 'driver_photo_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $photo = $file->storeAs('images/drivers', $fileName, 'public');
            }

            // Format SSN
            $ssn = preg_replace('/[^0-9]/', '', $request->ssn);

            // Get country/state names from IDs
            $countryName = Country::find($request->country)?->name ?? $request->country;
            $stateName = $request->state ? (is_numeric($request->state) ?
                State::find($request->state)?->name : $request->state) : null;

            // Update driver
            $driver->update([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix,
                'date_of_birth' => $request->date_of_birth,
                'ssn' => $ssn,
                'main_phone' => $request->main_phone,
                'alt_phone' => $request->alt_phone,
                'email' => $request->email,
                'medical_certificate_expiration_date' => $request->medical_certificate_expiration_date,
                'photo' => $photo,
                'business_name' => $request->business_name,
                'employer_identification_number' => $request->employer_identification_number,
                'federal_tax_classification' => $request->federal_tax_classification,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $stateName,
                'country' => $countryName,
                'postal_code' => $request->postal_code,
                'twic_card' => $request->boolean('twic_card'),
                'passport' => $request->boolean('passport'),
            ]);

            // Create residence addresses
            if ($request->has('residence_address')) {
                $residences = [];
                foreach ($request->residence_address as $index => $address) {
                    // Only create if address is provided
                    if (!empty(trim($address ?? ''))) {
                        $resCountry = $request->residence_country[$index] ?? null;
                        $resState = $request->residence_state[$index] ?? null;

                        $residences[] = [
                            'driver_id' => $driver->id,
                            'address' => $address,
                            'city' => $request->residence_city[$index] ?? null,
                            'state' => is_numeric($resState) ? \App\Models\State::find($resState)?->name : $resState,
                            'country' => is_numeric($resCountry) ? Country::find($resCountry)?->name : $resCountry,
                            'zip' => $request->residence_postal_code[$index] ?? null,
                            'is_current' => $index === 0, // First address is current
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($residences)) {
                    DB::table('residence_addresses')->insert($residences);
                }
            }

            // Create license - Get names from IDs
            $licenseCountry = Country::find($request->license_country)?->name ?? $request->license_country;
            $licenseState = $request->license_state ? (is_numeric($request->license_state) ?
                State::find($request->license_state)?->name : $request->license_state) : null;

            DB::table('licenses')->insert([
                'driver_id' => $driver->id,
                'first_name' => $request->license_first_name,
                'last_name' => $request->license_last_name,
                'issued' => $request->license_issued,
                'expires' => $request->license_expires,
                'country' => $licenseCountry,
                'state' => $licenseState,
                'class' => $request->license_class,
                'license_number' => $request->license_number,
                'repeat_license_number' => $request->repeat_license_number,
                'is_h_placarded_hazmat' => $request->boolean('is_h_placarded_hazmat'),
                'is_n_tank_vehicle' => $request->boolean('is_n_tank_vehicle'),
                'is_p_passengers' => $request->boolean('is_p_passengers'),
                'is_t_double_trailer' => $request->boolean('is_t_double_trailer'),
                'is_s_school_bus' => $request->boolean('is_s_school_bus'),
                'is_x_placarded_hazmat' => $request->boolean('is_x_placarded_hazmat'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create experiences
            $experiences = [];
            foreach ($request->equipment_class as $index => $equipmentClass) {
                // Only create if experience is yes and dates are provided
                if (($request->experience[$index] ?? 'no') === 'yes') {
                    $experiences[] = [
                        'driver_id' => $driver->id,
                        'equipment_class' => $equipmentClass,
                        'experience' => $request->experience[$index] ?? 'no',
                        'from_date' => $request->experience_from_date[$index] ?? null,
                        'to_date' => $request->experience_to_date[$index] ?? null,
                        'approx_miles' => $request->approx_miles[$index] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (!empty($experiences)) {
                DB::table('experiences')->insert($experiences);
            }

            // Handle accidents
            if ($request->accident === 'yes' && $request->has('accident_date')) {
                $accidents = [];
                foreach ($request->accident_date as $index => $date) {
                    if (!empty($date)) {
                        $accidents[] = [
                            'driver_id' => $driver->id,
                            'accident' => 'yes',
                            'accident_date' => $date,
                            'accident_location' => $request->accident_location[$index] ?? null,
                            'number_of_injuries' => $request->number_of_injuries[$index] ?? null,
                            'number_of_fatalities' => $request->number_of_fatalities[$index] ?? null,
                            'hazmat_spill' => $request->hazmat_spill[$index] ?? 'no',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($accidents)) {
                    DB::table('accidents')->insert($accidents);
                }
            } else {
                // Create default no accident record
                DB::table('accidents')->insert([
                    'driver_id' => $driver->id,
                    'accident' => 'no',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Handle violations
            if ($request->violation === 'yes' && $request->has('violation_date')) {
                $violations = [];
                foreach ($request->violation_date as $index => $date) {
                    if (!empty($date)) {
                        $violations[] = [
                            'driver_id' => $driver->id,
                            'violation' => 'yes',
                            'violation_date' => $date,
                            'violation_location' => $request->violation_location[$index] ?? null,
                            'offense' => $request->offense[$index] ?? null,
                            'vehicle_type' => $request->vehicle_type[$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($violations)) {
                    DB::table('violations')->insert($violations);
                }
            } else {
                // Create default no violation record
                DB::table('violations')->insert([
                    'driver_id' => $driver->id,
                    'violation' => 'no',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create forfeiture
            DB::table('forfeitures')->insert([
                'driver_id' => $driver->id,
                'denied_license' => $request->denied_license,
                'license_revoked' => $request->license_revoked,
                'forfeitures' => $request->forfeitures,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create employment records
            if ($request->has('employer_name')) {
                $employmentRecords = [];
                foreach ($request->employer_name as $index => $employerName) {
                    if (!empty(trim($employerName ?? ''))) {
                        $empCountry = $request->employer_record_country[$index] ?? null;
                        $empState = $request->employer_record_state[$index] ?? null;

                        $employmentRecords[] = [
                            'driver_id' => $driver->id,
                            'employer_name' => $employerName,
                            'employer_record_address' => $request->employer_record_address[$index] ?? null,
                            'employer_record_city' => $request->employer_record_city[$index] ?? null,
                            'employer_record_country' => is_numeric($empCountry) ? Country::find($empCountry)?->name : $empCountry,
                            'employer_record_state' => is_numeric($empState) ? \App\Models\State::find($empState)?->name : $empState,
                            'employer_record_postal_code' => $request->employer_record_postal_code[$index] ?? null,
                            'employer_record_phone' => $request->employer_record_phone[$index] ?? null,
                            'employer_record_fax' => $request->employer_record_fax[$index] ?? null,
                            'employer_record_email' => $request->employer_record_email[$index] ?? null,
                            'employer_record_position' => $request->employer_record_position[$index] ?? null,
                            'employer_record_date_from' => $request->employer_record_date_from[$index] ?? null,
                            'employer_record_date_to' => $request->employer_record_date_to[$index] ?? null,
                            'employer_record_reason_for_leaving' => $request->employer_record_reason_for_leaving[$index] ?? null,
                            'employed_regulations' => $request->employed_regulations[$index] ?? 'no',
                            'safety_sensitive_function' => $request->safety_sensitive_function[$index] ?? 'no',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($employmentRecords)) {
                    DB::table('employment_records')->insert($employmentRecords);
                }
            }

            // Update session
            Session::put('current_step', 2);

            DB::commit();

            toastr()->success('Basic information saved successfully!');
            return redirect()->route('public.application.step2', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 1 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to save information. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 2: Driver License Upload
     */
    public function step2($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 2;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step2-license', compact(
            'company',
            'driver',
            'currentStep',
            'driver_document',
            'isEditMode'
        ));
    }

    public function storeStep2(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'license_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'license_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Handle license front upload
            $licenseFront = $request->file('license_front');
            $frontPath = $licenseFront->storeAs(
                'images/documents',
                'license_front_' . time() . '_' . uniqid() . '.' . $licenseFront->getClientOriginalExtension(),
                'public'
            );

            // Handle license back upload
            $licenseBack = $request->file('license_back');
            $backPath = $licenseBack->storeAs(
                'images/documents',
                'license_back_' . time() . '_' . uniqid() . '.' . $licenseBack->getClientOriginalExtension(),
                'public'
            );

            // Update or create driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'license_front' => $frontPath,
                    'license_back' => $backPath,
                ]
            );

            // Update session
            Session::put('current_step', 3);

            DB::commit();

            toastr()->success('License uploaded successfully!');
            return redirect()->route('public.application.step3', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 2 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to upload license. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 3: Medical Card Upload
     */
    public function step3($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 3;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step3-medical-card', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument',
            'isEditMode'
        ));
    }

    public function storeStep3(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'medical_card' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Handle medical card upload
            $medicalCard = $request->file('medical_card');
            $medicalCardPath = $medicalCard->storeAs(
                'images/documents/public',
                'medical_card_' . time() . '_' . uniqid() . '.' . $medicalCard->getClientOriginalExtension(),
                'public'
            );

            // Update driver with medical certificate date
            $driver->update([
                'medical_certificate_expiration_date' => $request->medical_certificate_expiration_date,
            ]);

            // Update or create driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'medical_card' => $medicalCardPath,
                ]
            );

            // Update session
            Session::put('current_step', 4);

            DB::commit();

            toastr()->success('Medical card uploaded successfully!');
            return redirect()->route('public.application.step4', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 3 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to upload medical card. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 4: Forfeiture Document Upload
     */
    public function step4($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 4;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step4-forfeiture', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument',
            'isEditMode'
        ));
    }

    public function storeStep4(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'forfeiture_document' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Handle forfeiture document upload if provided
            $forfeiturePath = null;
            if ($request->hasFile('forfeiture_document')) {
                $forfeitureDoc = $request->file('forfeiture_document');
                $forfeiturePath = $forfeitureDoc->storeAs(
                    'images/documents/public',
                    'forfeiture_' . time() . '_' . uniqid() . '.' . $forfeitureDoc->getClientOriginalExtension(),
                    'public'
                );
            }

            // Update or create driver document
            if ($forfeiturePath) {
                DriverDocument::updateOrCreate(
                    ['driver_id' => $driver->id],
                    [
                        'forfeiture_document' => $forfeiturePath,
                    ]
                );
            }

            // Update session
            Session::put('current_step', 5);

            DB::commit();

            toastr()->success('Forfeiture information saved successfully!');
            return redirect()->route('public.application.step5', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 4 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to save forfeiture information. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 5: Violation Record
     */
    public function step5($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 5;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();
        $violations = Violation::where('driver_id', $driver_id)->get();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step5-violation', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument',
            'violations'
        ));
    }

    public function storeStep5(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'violation' => 'required|in:yes,no',
            'driver_id' => 'required|exists:drivers,id',
            'violation_date' => 'required_if:violation,yes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'required_if:violation,yes|array',
            'violation_location.*' => 'nullable|string|max:255',
            'offense' => 'required_if:violation,yes|array',
            'offense.*' => 'nullable|string|max:255',
            'applicant_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Delete existing violations
            Violation::where('driver_id', $driver->id)->delete();

            if ($request->violation === 'yes' && $request->has('violation_date')) {
                foreach ($request->violation_date as $index => $date) {
                    if (!empty(trim($date ?? ''))) {
                        Violation::create([
                            'driver_id' => $driver->id,
                            'violation' => 'yes',
                            'violation_date' => $date,
                            'violation_location' => $request->violation_location[$index] ?? null,
                            'offense' => $request->offense[$index] ?? null,
                            'violation_record_signature' => $request->applicant_signature,
                            'violation_record_date_signed' => $request->date_signed,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } else {
                // Create default no violation record
                Violation::create([
                    'driver_id' => $driver->id,
                    'violation' => 'no',
                    'violation_record_signature' => $request->applicant_signature,
                    'violation_record_date_signed' => $request->date_signed,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'violation_record_signature' => $request->applicant_signature,
                    'violation_record_date_signed' => $request->date_signed,
                ]
            );

            // Update session
            Session::put('current_step', 6);

            DB::commit();

            toastr()->success('Violation record saved successfully!');
            return redirect()->route('public.application.step6', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 5 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to save violation record. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 6: Alcohol & Drug Test Statement
     */
    public function step6($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 6;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step6-alcohol-drug', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument'
        ));
    }

    public function storeStep6(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'drug_test_question_1' => 'required|in:yes,no',
            'drug_test_question_2' => 'required|in:yes,no,n/a',
            'applicant_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Update driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'drug_test_question_1' => $request->drug_test_question_1,
                    'drug_test_question_2' => $request->drug_test_question_2,
                    'drug_test_signature' => $request->applicant_signature,
                    'drug_test_date_signed' => $request->date_signed,
                ]
            );

            // Update session
            Session::put('current_step', 7);

            DB::commit();

            toastr()->success('Alcohol and drug test statement saved successfully!');
            return redirect()->route('public.application.step7', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 6 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to save statement. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 7: FMCSA Clearinghouse Consent
     */
    public function step7($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 7;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step7-fmcsa-consent', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument'
        ));
    }

    public function storeStep7(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'employee_signature' => 'required|string|max:255',
            'consent_agreement' => 'required|in:1',
            'date_signed' => 'required|date',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Update driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'fmcsa_consent' => true,
                    'fmcsa_consent_date' => now(),
                    'fmcsa_consent_signature' => $request->employee_signature,
                    'fmcsa_consent_agreement' => $request->consent_agreement,
                    'fmcsa_date_signed' => $request->date_signed,
                ]
            );

            // Update session
            Session::put('current_step', 8);

            DB::commit();

            toastr()->success('FMCSA Clearinghouse consent saved successfully!');
            return redirect()->route('public.application.step8', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 7 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to save consent. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 8: PSP Driver Disclosure & Authorization
     */
    public function step8($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 8;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step8-psp', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument'
        ));
    }

    public function storeStep8(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'applicant_signature' => 'required|string|max:255',
            'authorization_agreement' => 'required|in:1',
            'date_signed' => 'required|date',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Update driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'psp_authorization' => true,
                    'psp_authorization_date' => now(),
                    'psp_authorization_signature' => $request->applicant_signature,
                    'psp_authorization_agreement' => $request->authorization_agreement,
                ]
            );

            // Update session
            Session::put('current_step', 9);

            DB::commit();

            toastr()->success('PSP Driver Disclosure & Authorization saved successfully!');
            return redirect()->route('public.application.step9', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 8 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to save authorization. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 9: Alcohol & Drug Testing Policy
     */
    public function step9($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::findOrFail($driver_id);
        $currentStep = 9;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();
        $policyPdf = PolicyPdf::first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step9-alcohol-drug-policy', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument',
            'policyPdf'
        ));
    }

    public function storeStep9(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'employee_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Update driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'alcohol_drug_test_policy_signature' => $request->employee_signature,
                    'alcohol_drug_test_policy_date' => $request->date_signed,
                ]
            );

            // Update session
            Session::put('current_step', 10);

            DB::commit();

            toastr()->success('Alcohol & Drug Testing Policy saved successfully!');
            return redirect()->route('public.application.step10', [
                'slug' => $slug,
                'driver_id' => $driver->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 9 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to save policy agreement. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * STEP 10: General Work Policy & Final Review
     */
    public function step10($slug, $driver_id, Request $request)
    {
        $this->checkApplicationSession($slug, $driver_id);

        $company = Company::where('slug', $slug)->firstOrFail();
        $driver = Driver::with(['driver_documents', 'licenses'])->findOrFail($driver_id);
        $currentStep = 10;
        $driverDocument = DriverDocument::where('driver_id', $driver_id)->first();
        $policyPdf = PolicyPdf::first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('application.steps.step10-general-policy', compact(
            'company',
            'driver',
            'currentStep',
            'driverDocument',
            'policyPdf'
        ));
    }

    public function storeStep10(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'employee_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
            'driver_id' => 'required|exists:drivers,id',
            'final_confirmation' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $this->checkApplicationSession($slug, $request->driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($request->driver_id);

            // Update driver document
            DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'general_work_policy_signature' => $request->employee_signature,
                    'general_work_policy_date' => $request->date_signed,
                ]
            );

            // Update driver status to pending for admin review
            $driver->update([
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            // Clear session (except phone for status check)
            $phone = Session::get('verified_phone');
            Session::forget([
                'application_started',
                'application_driver_id',
                'current_step',
                'application_session_token',
                'verified_company_slug',
                'verified_company_id',
                'phone_verified_at'
            ]);

            // Keep only phone for status check
            Session::put('last_application_phone', $phone);

            DB::commit();

            toastr()->success('Application submitted successfully! We will review it shortly.');
            return redirect()->route('public.application.complete', $slug);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step 10 Save Error: ' . $e->getMessage());
            toastr()->error('Failed to submit application. Please try again.');
            return back()->withInput();
        }
    }

    /**
     * Application Complete/Thank You Page
     */
    public function complete($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $phone = Session::get('last_application_phone');

        if ($phone) {
            $driver = Driver::where('main_phone', $phone)
                ->where('company_id', $company->id)
                ->where('source', 'public_application')
                ->latest()
                ->first();
        } else {
            $driver = null;
        }

        return view('application.complete', compact('company', 'driver'));
    }

    /**
     * Check Application Status
     */
    public function status($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        return view('application.status', compact('company'));
    }

    public function checkStatus(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'date_of_birth' => 'required|date',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        $phone = $this->formatPhoneForTwilio($request->phone);
        $company = Company::where('slug', $slug)->firstOrFail();

        $driver = Driver::where('company_id', $company->id)
            ->where('main_phone', $phone)
            ->where('date_of_birth', $request->date_of_birth)
            ->where('source', 'public_application')
            ->first();

        if ($driver) {
            $statusLabels = [
                'draft' => 'In Progress',
                'pending' => 'Under Review',
                'active' => 'Approved',
                'rejected' => 'Not Approved',
                'inactive' => 'Inactive',
            ];

            $status = $statusLabels[$driver->status] ?? $driver->status;

            return view('application.status-result', compact('company', 'driver', 'status'));
        } else {
            toastr()->error('No application found with those details.');
            return back()->withInput();
        }
    }

    /**
     * Save Progress (AJAX)
     */
    public function saveProgress(Request $request)
    {
        // Implement AJAX auto-save if needed
    }

    /**
     * Withdraw Application
     */
    public function withdraw(Request $request, $slug, $driver_id)
    {
        $this->checkApplicationSession($slug, $driver_id);

        DB::beginTransaction();
        try {
            $driver = Driver::findOrFail($driver_id);
            $driver->update([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
            ]);

            // Clear session
            Session::forget([
                'application_started',
                'application_driver_id',
                'current_step',
                'application_session_token'
            ]);

            DB::commit();

            toastr()->success('Application withdrawn successfully.');
            return redirect()->route('public.application.start', $slug);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Withdraw Error: ' . $e->getMessage());
            toastr()->error('Failed to withdraw application. Please try again.');
            return back();
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request, $slug)
    {
        $phone = $request->phone ?? Session::get('otp_verification_phone');

        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number not found.'
            ], 400);
        }

        $result = $this->otpService->resendOTP($phone);

        if ($result['success']) {
            Session::put([
                'otp_verification_phone' => $phone,
                'otp_method' => $result['method'],
                'otp_sent_at' => now()->timestamp
            ]);
        }

        return response()->json($result);
    }

    /**
     * Helper Methods
     */
    private function checkApplicationSession($slug, $driver_id = null)
    {
        if (
            !Session::has('application_started') ||
            !Session::has('application_driver_id') ||
            !Session::has('verified_phone')
        ) {

            toastr()->error('Please start the application process first.');
            return redirect()->route('public.application.start', $slug);
        }

        if ($driver_id && Session::get('application_driver_id') != $driver_id) {
            toastr()->error('Unauthorized access.');
            return redirect()->route('public.application.start', $slug);
        }
    }

    private function calculateCurrentStep($driver)
    {
        // Determine which step the user is on based on completed data
        $driverDocument = DriverDocument::where('driver_id', $driver->id)->first();

        if (!$driver->first_name || !$driver->last_name) {
            return 1;
        } elseif (!$driverDocument || (!$driverDocument->license_front && !$driverDocument->license_back)) {
            return 2;
        } elseif (!$driverDocument || !$driverDocument->medical_card) {
            return 3;
        } elseif (!$driverDocument || !$driverDocument->forfeiture_document) {
            return 4;
        } elseif (!$driverDocument || !$driverDocument->violation_record_signature) {
            return 5;
        } elseif (!$driverDocument || !$driverDocument->drug_test_signature) {
            return 6;
        } elseif (!$driverDocument || !$driverDocument->fmcsa_consent) {
            return 7;
        } elseif (!$driverDocument || !$driverDocument->psp_authorization) {
            return 8;
        } elseif (!$driverDocument || !$driverDocument->alcohol_drug_test_policy_signature) {
            return 9;
        } else {
            return 10;
        }
    }

    protected function formatPhoneForTwilio($phone)
    {
        $cleaned = preg_replace('/\D/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        if (strlen($cleaned) == 10) {
            return '+1' . $cleaned;
        } elseif (str_starts_with($cleaned, '880') && strlen($cleaned) == 13) {
            return '+880' . substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '91') && strlen($cleaned) == 12) {
            return '+91' . substr($cleaned, 2);
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        return '+' . $cleaned;
    }

    protected function getOtpFromRequest(Request $request)
    {
        if ($request->filled('otp')) {
            return $request->otp;
        }

        $digits = [
            $request->digit1,
            $request->digit2,
            $request->digit3,
            $request->digit4,
            $request->digit5,
            $request->digit6,
        ];

        $digits = array_filter($digits, function ($digit) {
            return !is_null($digit) && $digit !== '';
        });

        if (count($digits) === 6) {
            return implode('', $digits);
        }

        return '';
    }
}
