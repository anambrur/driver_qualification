{{-- resources/views/subscription/payment-success.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Payment Successful')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mb-3"
                        style="width:80px;height:80px">
                        <i class="bi bi-check-lg text-success" style="font-size:2.5rem"></i>
                    </div>
                </div>
                <h1 class="fw-bold mb-2">Payment Successful!</h1>
                <p class="lead text-muted mb-1">You are now subscribed to the <strong>{{ $plan->name }}</strong> plan.</p>
                <p class="text-muted mb-4">A confirmation email has been sent to your inbox.</p>

                <div class="d-flex gap-3 justify-content-center">
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary btn-lg">
                        <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                    </a>
                    <a href="{{ route('subscription.my') }}" class="btn btn-outline-secondary btn-lg">
                        View Subscription
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
