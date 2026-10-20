<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleDemoRequest;
use App\Services\Platform\DemoRequestService;
use Illuminate\Http\JsonResponse;

/**
 * The homepage "Schedule a demo" form.
 */
class DemoRequestController extends Controller
{
    public function store(ScheduleDemoRequest $request, DemoRequestService $demoRequests): JsonResponse
    {
        $demoRequests->submit($request->demoAttributes());

        return response()->json(['message' => 'Thanks! Our team will contact you shortly.'], 201);
    }
}
