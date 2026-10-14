<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessStripeEvent;
use App\Services\Stripe\StripeEventProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

/**
 * Verifies and stores the event, then answers 200; ProcessStripeEvent applies it (07 §6.2).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeEventProcessor $processor): Response
    {
        try {
            $event = $processor->constructEvent($request->getContent(), (string) $request->header('Stripe-Signature', ''));
        } catch (SignatureVerificationException $e) {
            Log::warning('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);

            return response('Invalid payload', 400);
        }

        // A handled duplicate needs nothing more; a pending one is queued again (ShouldBeUnique).
        if ($row = $processor->receive($event)) {
            ProcessStripeEvent::dispatch($row->id);
        }

        return response('OK', 200);
    }
}
