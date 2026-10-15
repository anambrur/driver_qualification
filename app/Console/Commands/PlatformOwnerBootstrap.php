<?php

namespace App\Console\Commands;

use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Models\AuditLog;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Services\Platform\EmailIdentityService;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates the first two Platform Owners of a new environment (04 §3: at least two, so support
 * sessions can be approved four-eyes). Runs once: it refuses when any Platform Owner exists.
 * No password is taken on the command line; each owner gets a reset link.
 */
class PlatformOwnerBootstrap extends Command
{
    protected $signature = 'platform:bootstrap-owners {email1} {name1} {email2} {name2}';

    protected $description = 'Create the first two Platform Owners and email them a link to set their password';

    public function handle(EmailIdentityService $emails): int
    {
        $owners = [
            [$emails->normalize($this->argument('email1')), (string) $this->argument('name1')],
            [$emails->normalize($this->argument('email2')), (string) $this->argument('name2')],
        ];

        $invalid = Validator::make(
            ['email1' => $owners[0][0], 'email2' => $owners[1][0], 'name1' => $owners[0][1], 'name2' => $owners[1][1]],
            ['email1' => ['required', 'email'], 'email2' => ['required', 'email', 'different:email1'], 'name1' => ['required'], 'name2' => ['required']],
        );

        if ($invalid->fails()) {
            $this->error(implode(' ', $invalid->errors()->all()));

            return self::FAILURE;
        }

        (new PlatformRoleSeeder)->run();
        $role = Role::query()->where('team_id', 0)->where('system_key', RoleTemplates::PLATFORM_OWNER)->firstOrFail();

        if (DB::table('model_has_roles')->where('team_id', 0)->where('role_id', $role->id)->exists()) {
            $this->error('Platform Owners already exist. Manage staff from /platform/staff.');

            return self::FAILURE;
        }

        try {
            // Both or neither: the outer transaction rolls the first back if the second is taken.
            // That holds the first row uncommitted past its email lock; for a one-off command on a
            // new environment the unique index and integrity query 9 cover that window.
            $created = DB::transaction(fn () => array_map(
                fn (array $owner) => $emails->claim($owner[0], function (string $email) use ($owner, $role) {
                    $user = PlatformUser::query()->create(['name' => $owner[1], 'email' => $email, 'password' => Str::random(64), 'status' => 'active']);
                    TeamScope::run(0, fn () => $user->assignRole($role));
                    AuditLog::recordSystem(null, 'platform.owner_bootstrapped', $user, ['email' => $email]);

                    return $user;
                }),
                $owners,
            ));
        } catch (ValidationException $e) {
            $this->error(implode(' ', $e->errors()['email'] ?? [$e->getMessage()]));

            return self::FAILURE;
        }

        foreach ($created as $user) {
            Password::broker('platform_users')->sendResetLink(['email' => $user->email]);
        }

        $this->info('Two Platform Owners created. Each was emailed a link to set their password.');

        return self::SUCCESS;
    }
}
