<?php

namespace Tests\Feature\Modules\PublicApplication;

use App\Models\Company;
use App\Models\Driver;
use App\Services\OTPService;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Illuminate\Http\UploadedFile;
use Mockery;
use Mockery\MockInterface;

/**
 * Fixtures for the public 10-step application (no auth, session-based).
 */
final class Applicant
{
    public static function company(array $attributes = []): Company
    {
        return CompanyFactory::new()->create($attributes);
    }

    /**
     * A draft created by the OTP step, as verifyOtp() creates it.
     */
    public static function draft(?Company $company = null, array $attributes = []): Driver
    {
        $company ??= self::company();

        return DriverFactory::new()->forCompany($company)->publicDraft()->create($attributes);
    }

    /**
     * The session verifyOtp() grants to the applicant who owns $driver.
     *
     * @return array<string, mixed>
     */
    public static function session(Driver $driver, ?string $slug = null): array
    {
        $driver->loadMissing('company');

        return [
            'verified_phone' => $driver->main_phone,
            'verified_company_slug' => $slug ?? $driver->company->slug,
            'verified_company_id' => $driver->company_id,
            'phone_verified_at' => now()->timestamp,
            'application_started' => true,
            'application_driver_id' => $driver->id,
            'current_step' => 1,
        ];
    }

    /**
     * Binds a mocked OTPService so no SMS (Vonage) is ever sent.
     */
    public static function fakeOtp(): MockInterface
    {
        $mock = Mockery::mock(OTPService::class);
        $mock->allows('validatePhoneNumber')->andReturn(true)->byDefault();
        $mock->allows('checkOTPStatus')->andReturn(['can_resend' => true, 'attempts_count' => 0, 'max_attempts' => 3])->byDefault();
        $mock->allows('sendOTP')->andReturn(['success' => true, 'method' => 'direct_sms', 'message' => 'sent'])->byDefault();
        $mock->allows('resendOTP')->andReturn(['success' => true, 'method' => 'direct_sms', 'message' => 'sent'])->byDefault();
        $mock->allows('verifyOTP')->andReturn(['success' => true, 'message' => 'ok'])->byDefault();
        $mock->allows('getOtpExpiryTime')->andReturn(null)->byDefault();

        app()->instance(OTPService::class, $mock);

        return $mock;
    }

    /**
     * Valid POST payloads for steps 2..10, keyed by step number.
     *
     * @return array<string, mixed>
     */
    public static function stepPayload(int $step, Driver $driver): array
    {
        $signed = ['date_signed' => now()->toDateString()];

        return ['driver_id' => $driver->id] + match ($step) {
            2 => [
                'license_front' => UploadedFile::fake()->image('front.jpg'),
                'license_back' => UploadedFile::fake()->image('back.jpg'),
            ],
            3 => ['medical_card' => UploadedFile::fake()->image('medical.jpg')],
            4 => ['forfeiture_document' => UploadedFile::fake()->image('forfeiture.jpg')],
            5 => ['violation' => 'no', 'applicant_signature' => 'Attacker'] + $signed,
            6 => ['drug_test_question_1' => 'no', 'drug_test_question_2' => 'no', 'applicant_signature' => 'Attacker'] + $signed,
            7 => ['employee_signature' => 'Attacker', 'consent_agreement' => '1'] + $signed,
            8 => ['applicant_signature' => 'Attacker', 'authorization_agreement' => '1'] + $signed,
            9, 10 => ['employee_signature' => 'Attacker'] + $signed,
        };
    }

    /**
     * A valid step 1 (basic information) payload.
     *
     * @return array<string, mixed>
     */
    public static function step1Payload(Driver $driver, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Roe',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'ssn' => '123-45-6789',
            'main_phone' => $driver->main_phone,
            'email' => 'jane.roe@example.com',
            'medical_certificate_expiration_date' => now()->addYear()->toDateString(),
            'address' => '1 Main St',
            'city' => 'Austin',
            'state' => 'Texas',
            'country' => 'United States',
            'postal_code' => '73301',
            'accident' => 'no',
            'violation' => 'no',
            'denied_license' => 'no',
            'license_revoked' => 'no',
            'license_first_name' => 'Jane',
            'license_last_name' => 'Roe',
            'license_issued' => now()->subYears(5)->toDateString(),
            'license_expires' => now()->addYears(3)->toDateString(),
            'license_country' => 'United States',
            'license_state' => 'Texas',
            'license_class' => 'A',
            'license_number' => 'D1234567',
            'repeat_license_number' => 'D1234567',
            'equipment_class' => ['Straight Truck'],
            'experience' => ['no'],
        ], $overrides);
    }
}
