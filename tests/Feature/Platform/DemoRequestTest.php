<?php

use App\Mail\DemoRequestReceivedMail;
use App\Models\AuditLog;
use App\Models\DemoRequest;
use App\Models\SiteSetting;
use Database\Factories\DemoRequestFactory;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Actors;

/*
| The homepage "Schedule a demo" form: public, throttled, honeypot-guarded; requests land in
| Platform > Content > Demo requests and are mailed to support.
*/

beforeEach(function () {
    Mail::fake();
});

it('stores a demo request and queues the mail to the support address', function () {
    SiteSetting::getSettings()->update(['support_email' => 'support@example.com', 'email' => 'hello@example.com']);

    $this->postJson(route('demo-requests.store'), ['name' => 'Lee Park', 'email' => 'lee@fleet.test', 'company' => 'Park Haulage', 'message' => '40 trucks'])
        ->assertCreated();

    $request = DemoRequest::query()->sole();
    expect([$request->name, $request->email, $request->company, $request->handled_at])->toBe(['Lee Park', 'lee@fleet.test', 'Park Haulage', null]);
    Mail::assertQueued(DemoRequestReceivedMail::class, fn ($mail) => $mail->hasTo('support@example.com') && $mail->demoRequest->is($request));
});

it('falls back to the general email and skips the mail when neither is set', function () {
    SiteSetting::getSettings()->update(['email' => 'hello@example.com']);
    $this->postJson(route('demo-requests.store'), ['name' => 'A', 'email' => 'a@fleet.test'])->assertCreated();
    Mail::assertQueued(DemoRequestReceivedMail::class, fn ($mail) => $mail->hasTo('hello@example.com'));

    SiteSetting::getSettings()->update(['email' => null]);
    $this->postJson(route('demo-requests.store'), ['name' => 'B', 'email' => 'b@fleet.test'])->assertCreated();
    Mail::assertQueuedCount(1);
});

it('rejects a missing email and a filled honeypot', function () {
    $this->postJson(route('demo-requests.store'), ['name' => 'Bot', 'email' => 'bot@spam.test', 'website' => 'https://spam.test'])
        ->assertUnprocessable()->assertJsonValidationErrors('website');
    $this->postJson(route('demo-requests.store'), ['name' => 'Lee'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(DemoRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('throttles the form', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson(route('demo-requests.store'), ['name' => "N{$i}", 'email' => "n{$i}@fleet.test"])->assertCreated();
    }

    $this->postJson(route('demo-requests.store'), ['name' => 'N6', 'email' => 'n6@fleet.test'])->assertTooManyRequests();
});

it('lists open requests, marks one handled and deletes one, with audit rows', function () {
    $owner = Actors::platformOwner();
    $open = DemoRequestFactory::new()->create(['name' => 'Open Olive']);
    DemoRequestFactory::new()->handled()->create(['name' => 'Done Dora']);

    $this->actingAs($owner, 'platform')->get(route('platform.content.demo-requests.index'))
        ->assertOk()->assertSee('Open Olive')->assertDontSee('Done Dora');
    $this->get(route('platform.content.demo-requests.index', ['status' => 'handled']))
        ->assertOk()->assertSee('Done Dora')->assertDontSee('Open Olive');

    $this->put(route('platform.content.demo-requests.update', $open))->assertRedirect();
    expect($open->fresh()->handled_at)->not->toBeNull()
        ->and($open->fresh()->handled_by)->toBe($owner->id);

    $this->delete(route('platform.content.demo-requests.destroy', $open))->assertRedirect();
    expect(DemoRequest::query()->whereKey($open->id)->exists())->toBeFalse()
        ->and(AuditLog::whereIn('action', ['demo_request.handled', 'demo_request.deleted'])->count())->toBe(2);
});
