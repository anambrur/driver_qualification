{{-- resources/views/subscription/payment-cancel.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Payment Cancelled')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 mb-3"
                        style="width:80px;height:80px">
                        <i class="bi bi-x-lg text-warning" style="font-size:2.5rem"></i>
                    </div>
                </div>
                <h1 class="fw-bold mb-2">Payment Cancelled</h1>
                <p class="lead text-muted mb-4">Your payment was not completed. No charges were made.</p>

                <div class="d-flex gap-3 justify-content-center">
                    <a href="{{ route('subscription.plans') }}" class="btn btn-primary btn-lg">
                        Try Again
                    </a>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-lg">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
