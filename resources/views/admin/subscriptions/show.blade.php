@extends('layouts.main-layout')

@section('title', 'Manage User Subscriptions')

@section('content')
    <div class="p-4 sm:p-6">

        <div class="mb-6 flex flex-col justify-between items-start">
            <a href="{{ route('admin.subscriptions.index') }}"
                class="text-sm text-gray-500 hover:underline mb-2 flex items-center">
                ← Back to Subscriptions
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subscriptions for {{ $user->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $user->email }}</p>
        </div>


        @if ($errors->any())
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Subscription History --}}
            <div class="col-span-1 lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Subscription History</h2>
                    </div>
                    <div class="p-0">
                        @forelse($subscriptions as $sub)
                            @php
                                // dd($sub);
                                $stripeSub = (!empty($sub->stripe_id) && isset($stripeSubscriptions[$sub->stripe_id]))
                                    ? $stripeSubscriptions[$sub->stripe_id]
                                    : null;
                            @endphp
                            <div
                                class="p-5 border-b border-gray-50 dark:border-gray-750 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-bold text-gray-900 dark:text-white text-lg">
                                                {{ $sub->plan->name ?? 'Unknown Plan' }}</h3>
                                            @php
                                                $colors = [
                                                    'active' => 'bg-green-100 text-green-800',
                                                    'trial' => 'bg-blue-100 text-blue-800',
                                                    'grace' => 'bg-yellow-100 text-yellow-800',
                                                    'expired' => 'bg-red-100 text-red-800',
                                                    'cancelled' => 'bg-gray-100 text-gray-800',
                                                    'suspended' => 'bg-orange-100 text-orange-800',
                                                ];
                                            @endphp
                                            <span
                                                class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colors[$sub->status] ?? 'bg-gray-100' }}">
                                                {{ $sub->stripe_status }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500">Method: <span
                                                class="capitalize">{{ $sub->payment_method ?? 'N/A' }}</span></p>
                                        <div
                                            class="mt-2 text-sm text-gray-600 dark:text-gray-400 grid grid-cols-2 gap-2 max-w-sm">
                                            <div><span class="font-medium text-gray-700 dark:text-gray-300">Started:</span>
                                                <br>{{ $sub->created_at?->format('M d, Y g:i A') ?? 'N/A' }}</div>
                                            <div><span class="font-medium text-gray-700 dark:text-gray-300">Ends:</span>
                                                <br>
                                                @php
                                                    $isCancelled = method_exists($sub, 'canceled') ? $sub->canceled() : !empty($sub->cancelled_at);
                                                    $isActive = method_exists($sub, 'active') ? $sub->active() : ($sub->stripe_status === 'active');
                                                    $isOnGrace = method_exists($sub, 'onGracePeriod') ? $sub->onGracePeriod() : false;
                                                @endphp

                                                @if ($sub->ends_at)
                                                    {{ $sub->ends_at->format('M d, Y g:i A') }}
                                                @elseif ($isCancelled || $isOnGrace)
                                                    <span class="text-gray-600 dark:text-gray-300">Ends at period end</span>
                                                @elseif ($isActive)
                                                    <span class="text-green-600 font-medium">Ongoing</span>
                                                @else
                                                    <span class="text-gray-500">—</span>
                                                @endif
                                            </div>
                                            @if ($sub->cancelled_at)
                                                <div><span class="font-medium text-red-500">Cancelled:</span>
                                                    <br>{{ $sub->cancelled_at->format('M d, Y g:i A') }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex flex-col gap-2 relative" x-data="{ open: false }">
                                        <button @click="open = !open"
                                            class="text-gray-500 hover:text-gray-700 px-2 py-1 rounded-md border border-gray-200 bg-white shadow-sm text-sm">
                                            Actions ▾
                                        </button>
                                        <div x-show="open" @click.away="open = false" style="display:none;"
                                            class="absolute top-full right-0 mt-1 w-32 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg z-10 py-1">
                                            @if (in_array($sub->stripe_status, ['active', 'trial', 'grace']))
                                                <form action="{{ route('admin.subscriptions.expire', $sub) }}"
                                                    method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-red-600 text-sm">Expire
                                                        Now</button>
                                                </form>
                                                <form action="{{ route('admin.subscriptions.suspend', $sub) }}"
                                                    method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-orange-600 text-sm">Suspend</button>
                                                </form>
                                            @elseif($sub->stripe_status === 'suspended')
                                                <form action="{{ route('admin.subscriptions.reactivate', $sub) }}"
                                                    method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-green-600 text-sm">Reactivate</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Expanded Stripe data (from live API) --}}
                                @if ($stripeSub)
                                    <div class="mt-4">
                                        @php
                                            $stripeCustomer = (!empty($stripeSub->customer) && !is_string($stripeSub->customer)) ? $stripeSub->customer : null;
                                            $stripeItems = $stripeSub->items->data ?? [];
                                            $pm = (!empty($stripeSub->default_payment_method) && !is_string($stripeSub->default_payment_method)) ? $stripeSub->default_payment_method : null;
                                            $latestInvoice = (!empty($stripeSub->latest_invoice) && !is_string($stripeSub->latest_invoice)) ? $stripeSub->latest_invoice : null;
                                            $currentPeriodStart = !empty($stripeSub->current_period_start) ? \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_start) : null;
                                            $currentPeriodEnd = !empty($stripeSub->current_period_end) ? \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end) : null;
                                        @endphp

                                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3">
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">Stripe details</div>
                                                    <div class="text-xs text-gray-500">Live data from Stripe (expanded)</div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                                        {{ ($stripeSub->status ?? '') === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                        {{ $stripeSub->status ?? 'unknown' }}
                                                    </span>
                                                    <a href="https://dashboard.stripe.com/subscriptions/{{ $stripeSub->id }}" target="_blank" rel="noreferrer"
                                                       class="text-xs px-2.5 py-1 rounded-md border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750">
                                                        Open in Stripe
                                                    </a>
                                                </div>
                                            </div>

                                            <div class="p-4 space-y-4">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/20 p-3">
                                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Subscription ID</div>
                                                        <div class="mt-1 font-mono text-xs break-all text-gray-900 dark:text-gray-100">{{ $stripeSub->id }}</div>
                                                    </div>
                                                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/20 p-3">
                                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Current period</div>
                                                        <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                                            {{ $currentPeriodStart?->format('M d, Y') ?? '—' }}
                                                            <span class="text-gray-400">→</span>
                                                            {{ $currentPeriodEnd?->format('M d, Y') ?? '—' }}
                                                        </div>
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            Renews: {{ !empty($stripeSub->cancel_at_period_end) ? 'No (cancels at period end)' : 'Yes' }}
                                                        </div>
                                                    </div>
                                                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/20 p-3">
                                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Billing</div>
                                                        <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                                            {{ $stripeSub->collection_method ?? '—' }}
                                                            @if(!empty($stripeSub->days_until_due))
                                                                <span class="text-gray-400">·</span> Due in {{ $stripeSub->days_until_due }} days
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            Currency: {{ strtoupper($stripeSub->currency ?? '—') }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                                                        <div class="flex items-center justify-between">
                                                            <div class="font-semibold text-sm text-gray-900 dark:text-white">Customer</div>
                                                            <div class="text-xs text-gray-500 font-mono break-all">
                                                                {{ is_string($stripeSub->customer ?? null) ? $stripeSub->customer : ($stripeCustomer->id ?? '—') }}
                                                            </div>
                                                        </div>
                                                        <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                                                            <div class="font-medium">
                                                                {{ $stripeCustomer->name ?? '—' }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 break-words">
                                                                {{ $stripeCustomer->email ?? '' }}
                                                            </div>
                                                            @if(!empty($stripeCustomer?->phone))
                                                                <div class="text-xs text-gray-500 break-words">{{ $stripeCustomer->phone }}</div>
                                                            @endif
                                                        </div>
                                                        @if(!empty($stripeCustomer?->address))
                                                            <div class="mt-2 text-xs text-gray-500">
                                                                {{ trim(collect([$stripeCustomer->address->line1 ?? null, $stripeCustomer->address->line2 ?? null, $stripeCustomer->address->city ?? null, $stripeCustomer->address->state ?? null, $stripeCustomer->address->postal_code ?? null, $stripeCustomer->address->country ?? null])->filter()->implode(', ')) }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                                                        <div class="flex items-center justify-between">
                                                            <div class="font-semibold text-sm text-gray-900 dark:text-white">Payment method</div>
                                                            <div class="text-xs text-gray-500">{{ $pm ? 'Default' : '—' }}</div>
                                                        </div>
                                                        @if($pm && ($pm->type ?? null) === 'card')
                                                            <div class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                                                                <div class="font-medium capitalize">{{ $pm->card->brand ?? 'card' }} •••• {{ $pm->card->last4 ?? '----' }}</div>
                                                                <div class="text-xs text-gray-500">
                                                                    Exp {{ str_pad((string)($pm->card->exp_month ?? ''), 2, '0', STR_PAD_LEFT) }}/{{ $pm->card->exp_year ?? '' }}
                                                                </div>
                                                            </div>
                                                        @elseif($pm)
                                                            <div class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                                                                <div class="font-medium">{{ $pm->type ?? 'payment_method' }}</div>
                                                                <div class="text-xs text-gray-500 font-mono break-all">{{ $pm->id ?? '' }}</div>
                                                            </div>
                                                        @else
                                                            <div class="mt-2 text-sm text-gray-500">No default payment method attached.</div>
                                                        @endif

                                                        @if($latestInvoice)
                                                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                                                <div class="flex items-center justify-between">
                                                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">Latest invoice</div>
                                                                    <a href="https://dashboard.stripe.com/invoices/{{ $latestInvoice->id }}" target="_blank" rel="noreferrer"
                                                                       class="text-xs text-blue-600 hover:underline">
                                                                        View invoice
                                                                    </a>
                                                                </div>
                                                                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                                                                    <div>
                                                                        <div class="text-xs text-gray-500">Status</div>
                                                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $latestInvoice->status ?? '—' }}</div>
                                                                    </div>
                                                                    <div>
                                                                        <div class="text-xs text-gray-500">Total</div>
                                                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                                                            @if(isset($latestInvoice->total))
                                                                                {{ number_format(($latestInvoice->total ?? 0) / 100, 2) }} {{ strtoupper($latestInvoice->currency ?? '') }}
                                                                            @else
                                                                                —
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="rounded-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                                                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/20 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                                        <div class="font-semibold text-sm text-gray-900 dark:text-white">Subscription items</div>
                                                        <div class="text-xs text-gray-500">{{ count($stripeItems) }} item(s)</div>
                                                    </div>
                                                    <div class="overflow-x-auto">
                                                        <table class="w-full text-sm">
                                                            <thead class="bg-white dark:bg-gray-800">
                                                                <tr class="text-left text-xs text-gray-500">
                                                                    <th class="px-4 py-2 font-medium">Product</th>
                                                                    <th class="px-4 py-2 font-medium">Price</th>
                                                                    <th class="px-4 py-2 font-medium">Qty</th>
                                                                    <th class="px-4 py-2 font-medium">Interval</th>
                                                                    <th class="px-4 py-2 font-medium">Item ID</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                                @forelse($stripeItems as $item)
                                                                    @php
                                                                        $price = $item->price ?? null;
                                                                        $product = (!empty($price?->product) && !is_string($price->product)) ? $price->product : null;
                                                                        $unitAmount = $price->unit_amount ?? null;
                                                                        $currency = $price->currency ?? ($stripeSub->currency ?? null);
                                                                        $recurring = $price->recurring ?? null;
                                                                    @endphp
                                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                                                        <td class="px-4 py-3">
                                                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                                                {{ $product->name ?? ($price->nickname ?? '—') }}
                                                                            </div>
                                                                            <div class="text-xs text-gray-500 font-mono break-all">
                                                                                {{ is_string($price?->product ?? null) ? $price->product : ($product->id ?? '') }}
                                                                            </div>
                                                                        </td>
                                                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                                                            @if(!is_null($unitAmount))
                                                                                {{ number_format($unitAmount / 100, 2) }} {{ strtoupper($currency ?? '') }}
                                                                            @else
                                                                                —
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $item->quantity ?? '—' }}</td>
                                                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                                                            {{ $recurring->interval ?? '—' }}
                                                                            @if(!empty($recurring?->interval_count) && $recurring->interval_count > 1)
                                                                                ({{ $recurring->interval_count }})
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300 break-all">
                                                                            {{ $item->id ?? '—' }}
                                                                        </td>
                                                                    </tr>
                                                                @empty
                                                                    <tr>
                                                                        <td colspan="5" class="px-4 py-4 text-center text-gray-500">No items found.</td>
                                                                    </tr>
                                                                @endforelse
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <details class="rounded-lg border border-gray-100 dark:border-gray-700">
                                                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                        Debug: raw Stripe JSON
                                                    </summary>
                                                    <div class="px-4 pb-4">
                                                        <pre class="text-xs leading-relaxed whitespace-pre-wrap break-words font-mono bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto">{{ json_encode($stripeSub, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                </details>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-gray-500">
                                No subscription history found for this user.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Payment History --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Payment History</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Invoice</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Amount</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date/Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @php
                                    // The controller currently passes $payments as [].
                                    // Use Cashier invoices (live from Stripe) as the Payment History source.
                                    $invoiceRows = (!empty($payments) && count($payments)) ? $payments : ($invoices ?? []);
                                @endphp

                                @forelse ($invoiceRows as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            @php
                                                // Cashier invoice uses properties like $invoice->number (not a number() method).
                                                $invoiceNumber = null;
                                                if (is_object($row) && isset($row->number) && $row->number) {
                                                    $invoiceNumber = $row->number;
                                                } elseif (is_object($row) && method_exists($row, 'number') && $row->number()) {
                                                    $invoiceNumber = $row->number();
                                                } elseif (is_object($row) && isset($row->invoice_number) && $row->invoice_number) {
                                                    $invoiceNumber = $row->invoice_number;
                                                }
                                            @endphp

                                            @php
                                                // When Stripe invoice "number" is not set (draft/edge cases), show the Stripe invoice id.
                                                $invoiceId = null;
                                                if (is_object($row) && isset($row->id) && $row->id) {
                                                    $invoiceId = $row->id;
                                                } elseif (is_object($row) && method_exists($row, 'id')) {
                                                    $invoiceId = $row->id();
                                                }
                                            @endphp

                                            <div class="flex flex-col">
                                                <div>{{ $invoiceNumber ?? ($invoiceId ?? '—') }}</div>
                                                @if($invoiceNumber && $invoiceId)
                                                    <div class="text-[11px] text-gray-400 font-mono break-all">{{ $invoiceId }}</div>
                                                @endif
                                            </div>

                                            @if (is_object($row) && method_exists($row, 'hostedInvoiceUrl') && $row->hostedInvoiceUrl())
                                                <div class="mt-1">
                                                    <a href="{{ $row->hostedInvoiceUrl() }}" target="_blank" rel="noreferrer"
                                                       class="text-xs text-blue-600 hover:underline">
                                                        Open invoice
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @php
                                                // Cashier invoice total() already returns a formatted string (often including currency symbol).
                                                $amountText = null;
                                                if (is_object($row) && method_exists($row, 'total')) {
                                                    $amountText = $row->total();
                                                } elseif (is_object($row) && isset($row->amount)) {
                                                    $amountText = '$' . number_format(($row->amount ?? 0), 2) . ' ' . strtoupper($row->currency ?? 'USD');
                                                }
                                            @endphp
                                            {{ $amountText ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @php
                                                // Cashier invoice exposes $invoice->paid (bool), $invoice->status, and date() (Carbon).
                                                $paid = null;
                                                if (is_object($row) && isset($row->paid)) {
                                                    $paid = (bool) $row->paid;
                                                } elseif (is_object($row) && method_exists($row, 'paid')) {
                                                    $paid = (bool) $row->paid();
                                                } else {
                                                    $paid = (isset($row->status) && $row->status === 'paid');
                                                }

                                                $date = (is_object($row) && method_exists($row, 'date')) ? $row->date() : (isset($row->paid_at) ? $row->paid_at : null);
                                                $statusText = $paid ? 'Paid' : ($row->status ?? '—');
                                            @endphp

                                            @if ($paid)
                                                <span class="text-green-600 font-medium">Paid</span>
                                            @else
                                                <span class="text-orange-600 font-medium capitalize">{{ $statusText }}</span>
                                            @endif

                                            @if ($date)
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    {{ is_string($date) ? $date : $date->format('M d, Y') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-center text-gray-500">No invoices found for this user.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Manual Grant Sidebar --}}
            <div class="col-span-1">
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 sticky top-6">
                    <div
                        class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-t-xl">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Grant Subscription Manually</h2>
                        <p class="text-xs text-gray-500 mt-1 mt-1">Assign a new plan and cancel any existing active
                            subscriptions.</p>
                    </div>
                    <div class="p-5">
                        <form action="{{ route('admin.subscriptions.grant', $user) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="plan_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Plan<span
                                        class="text-red-500">*</span></label>
                                <select name="plan_id" id="plan_id" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="" disabled selected>-- Choose a Plan --</option>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}">{{ $plan->name }}
                                            ({{ $plan->price > 0 ? '$' . number_format($plan->price, 2) : 'Free' }} /
                                            {{ $plan->billing_cycle }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="custom_end_date"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom End Date
                                    (Optional)</label>
                                <input type="date" name="custom_end_date" id="custom_end_date"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Leave empty to use plan's default duration.</p>
                            </div>

                            <div class="mb-4">
                                <label for="notes"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes /
                                    Reason</label>
                                <textarea name="notes" id="notes" rows="2"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="E.g., Bank transfer received..."></textarea>
                            </div>

                            <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                Grant Plan
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Include Alpine.js if not already included in layout -->
    <script src="//unpkg.com/alpinejs" defer></script>
@endsection
