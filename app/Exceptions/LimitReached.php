<?php

namespace App\Exceptions;

use App\Features\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A create, restore, hire, invitation or reactivation would pass the plan's limit (05 §5).
 */
class LimitReached extends RuntimeException
{
    public function __construct(public readonly Feature $feature, public readonly int $limit)
    {
        parent::__construct("Your plan allows {$limit} {$feature->unit()}. Upgrade your plan to add more.");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'limit' => ['feature' => $this->feature->value, 'max' => $this->limit],
                'upgrade_url' => route('billing.index'),
            ], 422);
        }

        if (function_exists('toastr')) {
            toastr()->error($this->getMessage());
        }

        return back()->withErrors(['limit' => $this->getMessage()]);
    }
}
