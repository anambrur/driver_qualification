<?php
// ══════════════════════════════════════════════════════════════════
// app/Events/SubscriptionExpired.php
// ══════════════════════════════════════════════════════════════════

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public Subscription $subscription)
    {
    }
}
