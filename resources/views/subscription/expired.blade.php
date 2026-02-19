{{-- resources/views/subscription/expired.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Subscription Expired')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">

            <div class="mb-4">
                <i class="bi bi-clock-history text-danger" style="font-size: 4rem;"></i>
            </div>

            <h1 class="fw-bold mb-3">Your Subscription Has Expired</h1>

            @if($subscription)
                <p class="lead text-muted mb-2">
                    Your <strong>{{ $subscription->plan->name }}</strong> plan expired on
                    <strong>{{ $subscription->ends_at?->format('F d, Y') ?? 'N/A' }}</strong>.
                </p>
            @endif

            <p class="text-muted mb-5">
                Renew your subscription to restore full access to all features.
            </p>

            {{-- Quick Renew Button --}}
            @if($subscription)
                <a href="{{ route('subscription.renew') }}" class="btn btn-primary btn-lg me-3 mb-3">
                    <i class="bi bi-arrow-clockwise me-2"></i> Renew {{ $subscription->plan->name }}
                </a>
            @endif

            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary btn-lg mb-3">
                View All Plans
            </a>

            {{-- Plan highlights for upsell --}}
            @if($plans->count())
                <hr class="my-5">
                <h4 class="fw-semibold mb-4">Choose a Plan to Continue</h4>
                <div class="row g-3 text-start">
                    @foreach($plans->take(3) as $plan)
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold">{{ $plan->name }}</h6>
                                    <div class="h5 fw-bold text-primary mb-2">
                                        @if($plan->price == 0) Free
                                        @else ${{ number_format($plan->price, 2) }}<small class="fs-6 text-muted">/{{ $plan->billing_cycle }}</small>
                                        @endif
                                    </div>
                                    <a href="{{ route('subscription.checkout', $plan) }}"
                                       class="btn btn-sm btn-outline-primary w-100">Select</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-5">
                <p class="text-muted small">
                    Need help? <a href="mailto:{{ config('mail.from.address') }}">Contact Support</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection