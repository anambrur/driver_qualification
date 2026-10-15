@extends('layouts.platform')

@section('title', 'New asset transfer')

@php($input = 'mt-1 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90')

@section('content')
    <h1 class="mb-2 text-xl font-semibold text-gray-800 dark:text-white/90">New asset transfer</h1>
    <p class="mb-6 max-w-2xl text-sm text-gray-500">
        The vehicle or trailer is copied to the target company with its documents, service logs and schedules.
        At the source it is retired: it leaves its asset groups and its history stays there, read-only.
        Both company owners are notified.
    </p>

    <form method="POST" action="{{ route('platform.asset-transfers.store') }}" id="transfer-form"
        class="max-w-2xl space-y-4 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="block text-sm text-gray-700 dark:text-gray-300">Asset type
                <select name="asset_type" id="asset_type" class="{{ $input }}">
                    <option value="vehicle" @selected(old('asset_type') === 'vehicle')>Vehicle</option>
                    <option value="trailer" @selected(old('asset_type') === 'trailer')>Trailer</option>
                </select>
            </label>
            <label class="block text-sm text-gray-700 dark:text-gray-300">From company
                <select name="source_company_id" id="source_company_id" class="{{ $input }}" required>
                    <option value="">Choose…</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((int) old('source_company_id') === $company->id)>{{ $company->company_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm text-gray-700 dark:text-gray-300 md:col-span-2">Asset
                <select name="asset_id" id="asset_id" class="{{ $input }}" required>
                    <option value="">Choose the company first</option>
                </select>
            </label>
            <label class="block text-sm text-gray-700 dark:text-gray-300 md:col-span-2">To company
                <select name="target_company_id" class="{{ $input }}" required>
                    <option value="">Choose…</option>
                    @foreach ($companies->where('status', 'active') as $company)
                        <option value="{{ $company->id }}" @selected((int) old('target_company_id') === $company->id)>{{ $company->company_name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label class="block text-sm text-gray-700 dark:text-gray-300">Reason
            <textarea name="reason" rows="3" class="{{ $input }} h-auto py-2" required maxlength="1000">{{ old('reason') }}</textarea>
        </label>
        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="confirm" value="1" required>
            I confirm this transfer. It can't be undone from here, and the source company keeps only a read-only history of the asset.
        </label>
        <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Transfer</button>
    </form>
@endsection

@push('scripts')
    <script>
        (function() {
            const type = document.getElementById('asset_type');
            const company = document.getElementById('source_company_id');
            const asset = document.getElementById('asset_id');
            const url = @json(route('platform.asset-transfers.assets'));

            function load() {
                asset.replaceChildren(new Option('Loading…', ''));
                if (!company.value) {
                    asset.replaceChildren(new Option('Choose the company first', ''));
                    return;
                }
                fetch(url + '?' + new URLSearchParams({ company_id: company.value, asset_type: type.value }), { headers: { 'Accept': 'application/json' } })
                    .then(response => response.json())
                    .then(({ data }) => {
                        asset.replaceChildren(new Option(data.length ? 'Choose…' : 'No assets', ''));
                        data.forEach(item => asset.add(new Option(item.unit_no + (item.vin ? ' · ' + item.vin : ''), item.id)));
                    });
            }

            type.addEventListener('change', load);
            company.addEventListener('change', load);
            load();
        })();
    </script>
@endpush
