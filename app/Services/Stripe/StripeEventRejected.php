<?php

namespace App\Services\Stripe;

use RuntimeException;

/**
 * A Stripe event that can never apply (company mismatch, unknown price with no fallback).
 * The inbox row is marked failed without a retry.
 */
class StripeEventRejected extends RuntimeException {}
