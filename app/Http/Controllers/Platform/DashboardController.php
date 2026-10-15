<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\View\View;

/**
 * Platform overview from platform-owned tables only (D11): no tenant record is read.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'companies' => Company::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'subscriptions' => Subscription::query()->open()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }
}
