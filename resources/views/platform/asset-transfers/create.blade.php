@extends('layouts.platform')

@section('title', 'New asset transfer')

@php
    $companyOptions = fn ($rows) => ['' => 'Choose…'] + $rows->pluck('company_name', 'id')->all();
@endphp

@section('content')
    <x-ui.page-header title="New asset transfer" description="Moves one vehicle or trailer to another company.">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Asset transfers', 'href' => route('platform.asset-transfers.index')], ['label' => 'New transfer']]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <form method="POST" action="{{ route('platform.asset-transfers.store') }}" id="transfer-form" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.alert tone="info" title="What a transfer does">
            The vehicle or trailer is copied to the target company with its documents, service logs and schedules.
            At the source it is retired: it leaves its asset groups and its history stays there, read-only.
            Both company owners are notified.
        </x-ui.alert>

        <x-ui.card title="Asset">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Asset type" name="asset_type" for="asset_type" required>
                    <x-ui.select name="asset_type" id="asset_type" :options="['vehicle' => 'Vehicle', 'trailer' => 'Trailer']" />
                </x-ui.field>
                <x-ui.field label="From company" name="source_company_id" for="source_company_id" required>
                    <x-ui.select name="source_company_id" id="source_company_id" required :options="$companyOptions($companies)" />
                </x-ui.field>
                <x-ui.field label="Asset" name="asset_id" for="asset_id" required hint="Live vehicles or trailers of the source company, by unit number and VIN." class="sm:col-span-2">
                    <x-ui.select name="asset_id" id="asset_id" required :options="['' => 'Choose the company first']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Destination">
            <x-ui.field label="To company" name="target_company_id" required hint="Active companies only. It can't be the source company.">
                <x-ui.select name="target_company_id" required :options="$companyOptions($companies->where('status', 'active'))" />
            </x-ui.field>
        </x-ui.card>

        <x-ui.card title="Reason and confirmation">
            <div class="grid gap-4">
                <x-ui.field label="Reason" name="reason" required hint="Kept with the transfer record and shown in the history.">
                    <x-ui.textarea name="reason" rows="3" required maxlength="1000" />
                </x-ui.field>
                <x-ui.field name="confirm">
                    <x-ui.checkbox name="confirm" required label="I confirm this transfer"
                        description="It can't be undone from here, and the source company keeps only a read-only history of the asset." />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="outline" :href="route('platform.asset-transfers.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="arrow-right-left">Transfer</x-ui.button>
        </div>
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
