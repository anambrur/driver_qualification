<?php

// app/Http/Controllers/ApplicationFormController.php

namespace App\Http\Controllers;

use App\Http\Requests\PublicApplication\StoreApplicationDrugPolicyRequest;
use App\Http\Requests\PublicApplication\StoreApplicationDrugTestRequest;
use App\Http\Requests\PublicApplication\StoreApplicationFmcsaConsentRequest;
use App\Http\Requests\PublicApplication\StoreApplicationForfeitureRequest;
use App\Http\Requests\PublicApplication\StoreApplicationLicenseRequest;
use App\Http\Requests\PublicApplication\StoreApplicationMedicalCardRequest;
use App\Http\Requests\PublicApplication\StoreApplicationPspRequest;
use App\Http\Requests\PublicApplication\StoreApplicationStep1Request;
use App\Http\Requests\PublicApplication\StoreApplicationViolationRequest;
use App\Http\Requests\PublicApplication\StoreApplicationWorkPolicyRequest;
use App\Models\Company;
use App\Models\Country;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\PolicyPdf;
use App\Models\Violation;
use App\Services\Driver\DriverCrudService;
use App\Services\Driver\DriverDocumentWizardService;
use App\Services\OTPService;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class ApplicationFormController extends Controller
{
    public function __construct(
        protected OTPService $otpService,
        protected PhoneNumberService $phoneNumbers,
    ) {}

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
        if (! $company) {
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
                    $phone = $this->phoneNumbers->normalize((string) $value);

                    if (! $this->phoneNumbers->isValid($phone)) {
                        $fail('Please enter a valid phone number.');

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
                },
            ],
            'confirm_phone' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $phone = $this->phoneNumbers->normalize((string) $request->phone);
                    $confirmPhone = $this->phoneNumbers->normalize((string) $value);

                    if (! $this->phoneNumbers->isValid($confirmPhone)) {
                        $fail('Please enter a valid confirmation phone number.');

                        return;
                    }

                    if ($phone !== $confirmPhone) {
                        $fail('Phone and confirm phone must match.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        $phone = $this->formatPhoneNumber($request->phone);

        if (! $this->otpService->validatePhoneNumber($phone)) {
            toastr()->error('Invalid phone number format. Please enter a valid number.');

            return back()->withInput();
        }

        try {
            $status = $this->otpService->checkOTPStatus($phone);

            if (! $status['can_resend']) {
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
                    'otp_sent_at' => now()->timestamp,
                ]);

                toastr()->success('OTP sent successfully!');

                return redirect()->route('public.application.verify.otp', $slug);
            } else {
                toastr()->error($result['message']);

                return back()->withInput();
            }
        } catch (\Exception $e) {
            Log::error('OTP Send Error: '.$e->getMessage());
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

        if (! $phone) {
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

        if (! $phone) {
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
                'otp_sent_at',
            ]);

            // Check if driver already exists in draft
            $driver = Driver::where('company_id', $company->id)
                ->where('main_phone', $phone)
                ->where('status', 'draft')
                ->where('source', 'public_application')
                ->first();

            if (! $driver) {
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
                'application_session_token' => md5($phone.$company->id.time()),
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

        $phone = $this->formatPhoneNumber($request->phone);
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
                'application_session_token' => md5($phone.$company->id.time()),
            ]);

            toastr()->success('Application found! Redirecting to where you left off...');

            return redirect()->route('public.application.step'.$this->calculateCurrentStep($driver), [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } else {
            toastr()->error('No application found with those details.');

            return back()->withInput();
        }
    }

    /**
     * AJAX Verify Phone for Resuming Application
     */
    public function checkResumePhone(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Valid phone number is required.',
            ]);
        }

        $phone = $this->formatPhoneNumber($request->phone);
        $company = Company::where('slug', $slug)->first();

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid company identifier.',
            ]);
        }

        // Find driver
        $driver = Driver::where('company_id', $company->id)
            ->where('main_phone', $phone)
            ->where('source', 'public_application')
            ->whereIn('status', ['draft', 'pending'])
            ->first();

        if ($driver) {
            try {
                // Send OTP
                $result = $this->otpService->sendOTP($phone);

                if ($result['success']) {
                    return response()->json([
                        'success' => true,
                        'requires_otp' => true,
                        'phone' => $phone,
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                    ]);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while sending OTP.',
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No application found with this phone number.',
            ]);
        }
    }

    /**
     * AJAX Verify OTP for Resuming Application
     */
    public function verifyResumeOtpPhone(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Valid OTP code is required (6 digits).',
            ]);
        }

        $phone = $this->formatPhoneNumber($request->phone);
        $company = Company::where('slug', $slug)->first();

        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Invalid company.']);
        }

        // Verify OTP
        $result = $this->otpService->verifyOTP($phone, $request->otp);

        if ($result['success']) {
            // Find driver
            $driver = Driver::where('company_id', $company->id)
                ->where('main_phone', $phone)
                ->where('source', 'public_application')
                ->whereIn('status', ['draft', 'pending'])
                ->first();

            if ($driver) {
                // Restore Session properly for seamless resume logic
                Session::put([
                    'verified_phone' => $phone,
                    'verified_company_slug' => $slug,
                    'verified_company_id' => $company->id,
                    'application_started' => true,
                    'application_driver_id' => $driver->id,
                    'current_step' => $this->calculateCurrentStep($driver),
                    'application_session_token' => md5($phone.$company->id.time()),
                ]);

                // Formulate redirect to the specific step
                $redirectUrl = route('public.application.step'.$this->calculateCurrentStep($driver), [
                    'slug' => $slug,
                    'driver_id' => $driver->id,
                ]);

                return response()->json([
                    'success' => true,
                    'redirect' => $redirectUrl,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver record lost during verification.',
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Invalid or expired OTP code.',
            ]);
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

    public function storeStep1(StoreApplicationStep1Request $request, $slug, DriverCrudService $crud)
    {
        $this->checkApplicationSession($slug);

        try {
            $driverId = Session::get('application_driver_id');
            $driver = Driver::findOrFail($driverId);

            $data = array_merge($request->validated(), [
                'company_id' => $driver->company_id,
                'status' => $driver->status,
            ]);

            $crud->update($driver, $data, $request->file('photo'));

            Session::put('current_step', 2);

            toastr()->success('Basic information saved successfully!');

            return redirect()->route('public.application.step2', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 1 Save Error: '.$e->getMessage());
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

    public function storeStep2(StoreApplicationLicenseRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveLicense($driver, $request->file('license_front'), $request->file('license_back'));

            Session::put('current_step', 3);

            toastr()->success('License uploaded successfully!');

            return redirect()->route('public.application.step3', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 2 Save Error: '.$e->getMessage());
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

    public function storeStep3(StoreApplicationMedicalCardRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveMedicalCard($driver, $request->file('medical_card'));

            Session::put('current_step', 4);

            toastr()->success('Medical card uploaded successfully!');

            return redirect()->route('public.application.step4', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 3 Save Error: '.$e->getMessage());
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

    public function storeStep4(StoreApplicationForfeitureRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveForfeiture($driver, $request->file('forfeiture_document'));

            Session::put('current_step', 5);

            toastr()->success('Forfeiture information saved successfully!');

            return redirect()->route('public.application.step5', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 4 Save Error: '.$e->getMessage());
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
            'violations',
            'isEditMode'
        ));
    }

    public function storeStep5(StoreApplicationViolationRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveViolationRecord($driver, $request->validated());

            Session::put('current_step', 6);

            toastr()->success('Violation record saved successfully!');

            return redirect()->route('public.application.step6', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 5 Save Error: '.$e->getMessage());
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
            'driverDocument',
            'isEditMode'
        ));
    }

    public function storeStep6(StoreApplicationDrugTestRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveAlcoholAndDrugTest($driver, $request->validated());

            Session::put('current_step', 7);

            toastr()->success('Alcohol and drug test statement saved successfully!');

            return redirect()->route('public.application.step7', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 6 Save Error: '.$e->getMessage());
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
            'driverDocument',
            'isEditMode'
        ));
    }

    public function storeStep7(StoreApplicationFmcsaConsentRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveFmcsaConsent($driver, $request->validated());

            Session::put('current_step', 8);

            toastr()->success('FMCSA Clearinghouse consent saved successfully!');

            return redirect()->route('public.application.step8', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 7 Save Error: '.$e->getMessage());
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
            'driverDocument',
            'isEditMode'
        ));
    }

    public function storeStep8(StoreApplicationPspRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->savePspAuthorization($driver, $request->validated());

            Session::put('current_step', 9);

            toastr()->success('PSP Driver Disclosure & Authorization saved successfully!');

            return redirect()->route('public.application.step9', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 8 Save Error: '.$e->getMessage());
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
            'policyPdf',
            'isEditMode'
        ));
    }

    public function storeStep9(StoreApplicationDrugPolicyRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveAlcoholAndDrugTestPolicy($driver, $request->validated());

            Session::put('current_step', 10);

            toastr()->success('Alcohol & Drug Testing Policy saved successfully!');

            return redirect()->route('public.application.step10', [
                'slug' => $slug,
                'driver_id' => $driver->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Step 9 Save Error: '.$e->getMessage());
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
            'policyPdf',
            'isEditMode'
        ));
    }

    public function storeStep10(StoreApplicationWorkPolicyRequest $request, $slug, DriverDocumentWizardService $documents)
    {
        $this->checkApplicationSession($slug, $request->driver_id);

        try {
            $driver = Driver::findOrFail($request->driver_id);
            $documents->saveGeneralWorkPolicy($driver, $request->validated(), finalizeToPending: true);

            $phone = Session::get('verified_phone');
            Session::forget([
                'application_started',
                'application_driver_id',
                'current_step',
                'application_session_token',
                'verified_company_slug',
                'verified_company_id',
                'phone_verified_at',
            ]);

            Session::put('last_application_phone', $phone);

            toastr()->success('Application submitted successfully! We will review it shortly.');

            return redirect()->route('public.application.complete', $slug);
        } catch (\Exception $e) {
            Log::error('Step 10 Save Error: '.$e->getMessage());
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

        $phone = $this->formatPhoneNumber($request->phone);
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
                'application_session_token',
            ]);

            DB::commit();

            toastr()->success('Application withdrawn successfully.');

            return redirect()->route('public.application.start', $slug);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Withdraw Error: '.$e->getMessage());
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

        if (! $phone) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number not found.',
            ], 400);
        }

        $phone = $this->formatPhoneNumber($phone);

        $result = $this->otpService->resendOTP($phone);

        if ($result['success']) {
            Session::put([
                'otp_verification_phone' => $phone,
                'otp_method' => $result['method'],
                'otp_sent_at' => now()->timestamp,
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
            ! Session::has('application_started') ||
            ! Session::has('application_driver_id') ||
            ! Session::has('verified_phone')
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

        if (! $driver->first_name || ! $driver->last_name) {
            return 1;
        } elseif (! $driverDocument || (! $driverDocument->license_front && ! $driverDocument->license_back)) {
            return 2;
        } elseif (! $driverDocument || ! $driverDocument->medical_card) {
            return 3;
        } elseif (! $driverDocument || ! $driverDocument->forfeiture_document) {
            return 4;
        } elseif (! $driverDocument || ! $driverDocument->violation_record_signature) {
            return 5;
        } elseif (! $driverDocument || ! $driverDocument->drug_test_signature) {
            return 6;
        } elseif (! $driverDocument || ! $driverDocument->fmcsa_consent) {
            return 7;
        } elseif (! $driverDocument || ! $driverDocument->psp_authorization) {
            return 8;
        } elseif (! $driverDocument || ! $driverDocument->alcohol_drug_test_policy_signature) {
            return 9;
        } else {
            return 10;
        }
    }

    protected function formatPhoneNumber(string $phone): string
    {
        return $this->phoneNumbers->normalize($phone);
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
            return ! is_null($digit) && $digit !== '';
        });

        if (count($digits) === 6) {
            return implode('', $digits);
        }

        return '';
    }
}
