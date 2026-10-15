<?php

namespace App\Http\Middleware;

use App\Features\Feature;
use App\Models\User;
use App\Services\Entitlements\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * feature:<key> on a route group (05 §4). Blocks a feature the company is not entitled to;
 * a read-only feature passes, and Gate::before denies its writes.
 */
class EnsureFeature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $feature = Feature::from($feature);
        $user = $request->user();

        if ($user instanceof User && $user->company && Entitlements::for($user->company)->allows($feature)) {
            return $next($request);
        }

        $message = 'Your plan does not include '.$feature->label().'. Upgrade your plan to use it.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'feature' => $feature->value], 403);
        }

        abort(403, $message);
    }
}
