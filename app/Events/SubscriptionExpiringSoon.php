<?php
// ══════════════════════════════════════════════════════════════════
// app/Events/SubscriptionExpiringSoon.php
// ══════════════════════════════════════════════════════════════════

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringSoon
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining
    ) {
    }
}
