{{-- resources/views/subscription/my.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'My Subscription')

@section('content')
    <div class="container py-4">
        <h2 class="fw-bold mb-4">My Subscription</h2>

        @if ($subscription)
            <div class="row g-4 mb-5">
                {{-- Subscription Card --}}
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Current Plan</span>
                            <span class="badge bg-{{ $subscription->status_color }}">
                                {{ $subscription->status_label }}
                            </span>
                        </div>
                        <div class="card-body">
                            <h4 class="fw-bold">{{ $subscription->plan->name }}</h4>
                            <p class="text-muted">{{ $subscription->plan->description }}</p>

                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted">Started</td>
                                    <td>{{ $subscription->starts_at->format('M d, Y') }}</td>
                                </tr>
                                @if ($subscription->ends_at)
                                    <tr>
                                        <td class="text-muted">Expires</td>
                                        <td>
                                            {{ $subscription->ends_at->format('M d, Y') }}
                                            @if ($subscription->isExpiringSoon())
                                                <span class="badge bg-warning text-dark ms-1">
                                                    {{ $subscription->daysRemaining() }} days left
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="text-muted">Expires</td>
                                        <td><span class="badge bg-success">Lifetime</span></td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="text-muted">Auto-renew</td>
                                    <td>{{ $subscription->auto_renew ? 'Yes' : 'No' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2">
                            <a href="{{ route('admin.plans.index') }}" class="btn btn-sm btn-outline-primary">
                                Change Plan
                            </a>
                            @if ($subscription->isAccessible())
                                <a href="{{ route('subscription.renew') }}" class="btn btn-sm btn-outline-success">
                                    Renew
                                </a>
                                <form action="{{ route('subscription.cancel') }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to cancel?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Features Card --}}
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent fw-semibold">Plan Features</div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                @foreach ($subscription->plan->features ?? [] as $feature)
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>{{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                You don't have an active subscription.
                <a href="{{ route('subscription.plans') }}" class="alert-link">Browse Plans →</a>
            </div>
        @endif

        {{-- Payment History --}}
        <h4 class="fw-bold mb-3">Payment History</h4>
        @if ($payments->count())
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td><code>{{ $payment->invoice_number }}</code></td>
                                <td>{{ $payment->plan->name }}</td>
                                <td>${{ number_format($payment->amount, 2) }}</td>
                                <td>
                                    <span
                                        class="badge bg-{{ $payment->status === 'paid' ? 'success' : ($payment->status === 'pending' ? 'warning text-dark' : 'danger') }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td>{{ $payment->created_at->format('M d, Y') }}</td>
                                <td>
                                    @if ($payment->isPaid())
                                        <a href="{{ route('subscription.invoice.download', $payment) }}"
                                            class="btn btn-xs btn-outline-secondary">
                                            <i class="bi bi-download"></i> PDF
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        @else
            <p class="text-muted">No payment records found.</p>
        @endif
    </div>
@endsection
