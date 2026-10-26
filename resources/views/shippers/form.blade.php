{{-- Add and edit a shipper (req 4.4); the address comes from the map picker or is typed in. --}}
@extends('layouts.main-layout')

@section('title', $shipper->exists ? 'Edit shipper' : 'Add shipper')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="$shipper->exists ? $shipper->name : 'Add shipper'" description="Saved for reuse on trips.">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Shippers', 'href' => route('shippers.index')], ['label' => $shipper->exists ? $shipper->name : 'Add shipper']]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <div class="max-w-4xl space-y-6">
            <form method="POST" action="{{ $shipper->exists ? route('shippers.update', $shipper) : route('shippers.store') }}" class="space-y-6">
                @csrf
                @if ($shipper->exists)
                    @method('PUT')
                @endif

                <x-ui.card title="Shipper">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Name" name="name" required class="sm:col-span-2">
                            <x-ui.input name="name" :value="$shipper->name" required maxlength="150" />
                        </x-ui.field>
                        <x-ui.field label="Contact name" name="contact_name">
                            <x-ui.input name="contact_name" :value="$shipper->contact_name" maxlength="150" autocomplete="off" />
                        </x-ui.field>
                        <x-ui.field label="Phone" name="phone">
                            <x-ui.input type="tel" name="phone" :value="$shipper->phone" maxlength="20" autocomplete="off" />
                        </x-ui.field>
                        <x-ui.field label="Email" name="email" class="sm:col-span-2">
                            <x-ui.input type="email" name="email" :value="$shipper->email" maxlength="255" autocomplete="off" />
                        </x-ui.field>
                    </div>
                </x-ui.card>

                <x-ui.card title="Address" description="Search for the address or type it in. Drag the pin to the exact dock or gate.">
                    <x-maps.address-picker :value="$shipper" />
                </x-ui.card>

                <x-ui.card title="Notes">
                    <x-ui.field label="Notes" name="notes" hint="Hours, dock numbers, check-in rules.">
                        <x-ui.textarea name="notes" rows="3" maxlength="2000" :value="$shipper->notes" />
                    </x-ui.field>
                    <x-slot:footer class="justify-end">
                        <x-ui.button variant="outline" :href="route('shippers.index')">Back</x-ui.button>
                        <x-ui.button type="submit" icon="check">{{ $shipper->exists ? 'Save' : 'Add shipper' }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            @if ($shipper->exists)
                <x-ui.card title="Delete shipper" description="Removes it from the list. Trips that use it keep it. To stop offering it on new trips, deactivate it instead.">
                    <form method="POST" action="{{ route('shippers.destroy', $shipper) }}"
                        data-confirm="Delete this shipper?" data-confirm-button="Delete" data-confirm-text="{{ $shipper->name }} is removed from the list. Trips that use it keep it.">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="destructive" icon="trash-2">Delete shipper</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
