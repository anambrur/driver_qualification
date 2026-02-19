{{-- resources/views/subscription/plans.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Choose Your Plan')

@section('content')
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold">Choose Your Plan</h1>
        <p class="lead text-muted">Start with a free trial. No credit card required.</p>
    </div>

    @if($currentSubscription)
        <div class="alert alert-info d-flex align-items-center mb-4">
            <i class="bi bi-info-circle-fill me-2"></i>
            <div>
                You are currently on the <strong>{{ $currentSubscription->plan->name }}</strong> plan.
                @if($currentSubscription->daysRemaining() !== null)
                    <strong>{{ $currentSubscription->daysRemaining() }} days</strong> remaining.
                @else
                    Lifetime access.
                @endif
                <a href="{{ route('subscription.my') }}" class="alert-link ms-2">Manage subscription →</a>
            </div>
        </div>
    @endif

    <div class="row justify-content-center g-4">
        @foreach($plans as $plan)
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="card h-100 shadow-sm border-0 @if($plan->is_featured) border-primary border-2 @endif">
                    @if($plan->is_featured)
                        <div class="card-header bg-primary text-white text-center py-2">
                            <small class="fw-semibold text-uppercase letter-spacing-1">Most Popular</small>
                        </div>
                    @endif

                    <div class="card-body d-flex flex-column p-4">
                        <h4 class="card-title fw-bold">{{ $plan->name }}</h4>
                        <p class="text-muted small mb-3">{{ $plan->description }}</p>

                        {{-- Price --}}
                        <div class="mb-4">
                            @if($plan->price == 0)
                                <span class="display-6 fw-bold text-success">Free</span>
                            @else
                                <span class="display-6 fw-bold">${{ number_format($plan->price, 2) }}</span>
                                <span class="text-muted">/{{ $plan->billing_cycle }}</span>
                            @endif
                        </div>

                        {{-- Features --}}
                        <ul class="list-unstyled mb-4 flex-grow-1">
                            @foreach($plan->features ?? [] as $feature)
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>

                        {{-- CTA --}}
                        @auth
                            @if($currentSubscription && $currentSubscription->plan_id === $plan->id)
                                <button class="btn btn-outline-secondary" disabled>Current Plan</button>
                            @else
                                <a href="{{ route('subscription.checkout', $plan) }}"
                                   class="btn btn-{{ $plan->is_featured ? 'primary' : 'outline-primary' }} w-100">
                                    @if($plan->price == 0)
                                        Start Free Trial
                                    @else
                                        Get Started
                                    @endif
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-outline-primary w-100">Log in to Subscribe</a>
                        @endauth
                    </div>

                    @if($plan->billing_cycle === 'trial')
                        <div class="card-footer text-center text-muted small bg-transparent">
                            No credit card required
                        </div>
                    @elseif($plan->billing_cycle === 'yearly')
                        <div class="card-footer text-center text-success small bg-transparent">
                            Save ~17% vs monthly
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection