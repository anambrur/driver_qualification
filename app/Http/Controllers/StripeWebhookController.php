<?php

namespace App\Http\Controllers;

use App\Services\Stripe\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookProcessor $processor): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $processor->constructEvent($payload, $signature);
            $processor->handle($event);
        } catch (SignatureVerificationException $e) {
            Log::warning('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);

            return response('Invalid payload', 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Webhook handler error', 500);
        }

        return response('OK', 200);
    }
}
