<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApplicationSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user has verified phone and started application
        if (!session()->has('verified_phone') || !session()->has('application_driver_id')) {
            return redirect()->route('public.application.start', $request->route('slug'));
        }

        return $next($request);
    }
}
