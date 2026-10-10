<?php

use App\Jobs\SendSmsJob;
use App\Models\OtpVerification;
use App\Services\OTPService;
use App\Services\SmsService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Vonage\Client;

/*
| OTP-04: OtpVerification::prunable() existed without the Prunable trait (and nothing
| scheduled model:prune), so old OTP rows were never removed; generateOtpCode() was dead.
|
| OTP-05: SendSmsJob logged the full destination number while SmsService masks it.
*/

test('model:prune removes old OTP records', function () {
    $old = OtpVerification::create([
        'phone' => '+12092776341', 'otp' => 'x', 'method' => 'vonage_verify',
        'expires_at' => now()->subDays(8), 'is_used' => true,
    ]);
    $old->forceFill(['created_at' => now()->subDays(8)])->save();

    $fresh = OtpVerification::create([
        'phone' => '+12092776342', 'otp' => 'x', 'method' => 'vonage_verify',
        'expires_at' => now()->addMinutes(5), 'is_used' => false,
    ]);

    $this->artisan('model:prune', ['--model' => [OtpVerification::class]])->assertSuccessful();

    expect(OtpVerification::find($old->id))->toBeNull()
        ->and(OtpVerification::find($fresh->id))->not->toBeNull();
});

test('OTP pruning is scheduled', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'model:prune')
            && str_contains($e->command, 'OtpVerification'));

    expect($events)->not->toBeEmpty();
});

test('OTPService has no dead generateOtpCode helper', function () {
    expect(method_exists(OTPService::class, 'generateOtpCode'))->toBeFalse();
});

test('SendSmsJob masks the phone number when logging a failure', function () {
    Log::spy();

    $vonage = Mockery::mock(Client::class);
    $vonage->shouldReceive('messages->send')
        ->andThrow(new \Vonage\Client\Exception\Exception('boom'));
    app()->instance(Client::class, $vonage);

    $job = new SendSmsJob('+12092776341', 'hello', '15550000000');

    expect(fn () => $job->handle(app(SmsService::class)))
        ->toThrow(\App\Exceptions\Sms\SmsException::class);

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($msg, $ctx = []) => $msg === 'Queued SMS delivery failed'
            && $ctx['to'] === '*******6341');
    Log::shouldNotHaveReceived('error', fn ($msg, $ctx = []) => str_contains(json_encode($ctx), '2092776341'));
});
