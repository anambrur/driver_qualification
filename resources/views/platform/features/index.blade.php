@extends('layouts.platform')

@section('title', 'Features')

@php
    $options = collect($availability)->mapWithKeys(fn ($option) => [$option => ucfirst(str_replace('_', ' ', $option))])->all();
@endphp

@section('content')
    <x-ui.page-header title="Features" description="Changes apply to every company at once." />

    <x-ui.alert tone="info" class="mb-6">
        <p>
            <strong>General</strong>: plans may include it. <strong>Override only</strong>: only a company override grants it.
            <strong>Disabled</strong>: nobody has it.
        </p>
    </x-ui.alert>

    <x-ui.card flush class="py-0!">
        <x-ui.table>
            <thead>
                <tr><th>Feature</th><th>Group</th><th>Kind</th><th>Availability</th></tr>
            </thead>
            <tbody>
                @foreach ($features as $feature)
                    <tr>
                        <td data-label="Feature" data-primary>
                            @include('platform.partials.cell', ['title' => $feature->name, 'sub' => $feature->key])
                        </td>
                        <td data-label="Group">{{ $feature->group }}</td>
                        <td data-label="Kind"><span class="badge" data-variant="outline">{{ $feature->kind }}</span></td>
                        <td data-label="Availability">
                            <form method="POST" action="{{ route('platform.features.update', $feature->key) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <x-ui.select name="availability" :id="'availability-'.$feature->key" :value="$feature->availability" :options="$options"
                                    class="w-auto!" aria-label="Availability of {{ $feature->name }}" />
                                <x-ui.button type="submit" variant="outline" size="sm">Save</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection
