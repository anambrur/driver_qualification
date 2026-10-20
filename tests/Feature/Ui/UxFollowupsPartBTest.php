<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Actors;

/*
| UX follow-ups part B: audit_logs has an index on action for the viewer's prefix LIKE. The viewer's
| filters behave as before (paging and the action prefix are covered in PlatformPagesTest).
*/

it('indexes audit_logs.action on its own', function () {
    $index = collect(Schema::getIndexes('audit_logs'))->firstWhere('columns', ['action']);

    expect($index)->not->toBeNull()
        ->and($index['unique'])->toBeFalse();
});

it('filters the audit viewer by company, actor type, action prefix and date', function () {
    AuditLog::query()->forceCreate(['company_id' => 7, 'actor_type' => 'system', 'action' => 'plan.match', 'created_at' => now()->subDay()]);
    AuditLog::query()->forceCreate(['company_id' => 8, 'actor_type' => 'system', 'action' => 'plan.other_company', 'created_at' => now()->subDay()]);
    AuditLog::query()->forceCreate(['company_id' => 7, 'actor_type' => 'stripe', 'action' => 'plan.other_actor', 'created_at' => now()->subDay()]);
    AuditLog::query()->forceCreate(['company_id' => 7, 'actor_type' => 'system', 'action' => 'plan.too_old', 'created_at' => now()->subDays(10)]);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.audit.index', [
            'company_id' => 7, 'actor_type' => 'system', 'action' => 'plan.',
            'from' => now()->subDays(2)->toDateString(), 'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('plan.match')
        ->assertDontSee('plan.other_company')
        ->assertDontSee('plan.other_actor')
        ->assertDontSee('plan.too_old');
});

it('treats _ in the action filter as a literal', function () {
    AuditLog::recordSystem(null, 'plan_x.match');
    AuditLog::recordSystem(null, 'planXx.wildcard');

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.audit.index', ['action' => 'plan_']))
        ->assertOk()
        ->assertSee('plan_x.match')
        ->assertDontSee('planXx.wildcard');
})->skip(fn () => DB::getDriverName() === 'sqlite', 'SQLite LIKE has no default escape character');
