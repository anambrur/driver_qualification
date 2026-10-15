<?php

namespace App\Console\Commands;

use App\Services\Platform\SupportSessionService;
use Illuminate\Console\Command;

/**
 * Expires support requests nobody fully approved within the request window and ends sessions
 * past their end time (04 §4.1). ResolveTenant also ends an overdue session on its next request.
 */
class ExpireSupportSessions extends Command
{
    protected $signature = 'support-sessions:expire';

    protected $description = 'Expire stale support-session requests and end overdue sessions';

    public function handle(SupportSessionService $sessions): int
    {
        $this->info($sessions->expireStale().' support session(s) expired or ended.');

        return self::SUCCESS;
    }
}
