@extends('layouts.platform')

@section('title', $template->label())

@php
    $status = $template->isDraft() ? 'draft' : ($template->is_active ? 'active' : 'inactive');
    $route = fn (string $name, array $params = []) => route("platform.lookups.inspection-templates.$name", [$template, ...$params]);
@endphp

@section('content')
    <x-ui.page-header :title="$template->label()"
        :description="$editable ? 'Items can change until an inspection uses this version.' : 'An inspection used this version, so its items are fixed. Create a new version to change them.'">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Inspection templates', 'href' => route('platform.lookups.inspection-templates.index')], ['label' => $template->label()]]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @include('partials.ui.status-badge', ['status' => $status])
            @if ($template->isDraft())
                <form method="POST" action="{{ $route('destroy') }}" data-confirm="Delete this draft?" data-confirm-button="Delete" data-confirm-text="{{ $template->label() }} and its items are removed.">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="outline" icon="trash-2">Delete draft</x-ui.button>
                </form>
                <form method="POST" action="{{ $route('publish') }}" data-confirm="Publish {{ $template->label() }}?" data-confirm-button="Publish"
                    data-confirm-text="New pre-trip inspections use this version from now on. The current active version is deactivated.">
                    @csrf
                    <x-ui.button type="submit" icon="send">Publish</x-ui.button>
                </form>
            @else
                @if ($template->is_active)
                    <form method="POST" action="{{ $route('deactivate') }}" data-confirm="Deactivate {{ $template->label() }}?" data-confirm-button="Deactivate"
                        data-confirm-text="Companies with pre-trip inspections can't start a new inspection, so they can't start trips, until a version is active again.">
                        @csrf
                        <x-ui.button type="submit" variant="outline" icon="power">Deactivate</x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ $route('activate') }}" data-confirm="Activate {{ $template->label() }}?" data-confirm-button="Activate"
                        data-confirm-text="New pre-trip inspections use this version. The current active version is deactivated.">
                        @csrf
                        <x-ui.button type="submit" variant="outline" icon="power">Activate</x-ui.button>
                    </form>
                @endif
                <form method="POST" action="{{ $route('new-version') }}">
                    @csrf
                    <x-ui.button type="submit" icon="copy-plus">New version</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="max-w-4xl space-y-6">
        <x-ui.card title="Items" description="Grouped by section, in sort order. A required item must be answered (pass, fail or n/a) before the inspection can be completed.">
            @if ($items->isEmpty())
                <p class="pb-6 text-sm text-muted-foreground">No items yet.</p>
            @elseif ($editable)
                <div class="space-y-3 pb-6">
                    @foreach ($items as $item)
                        <div class="grid gap-2 border-b border-border pb-3 sm:grid-cols-[10rem_minmax(0,1fr)_5rem_auto_auto] sm:items-center">
                            <form id="item-{{ $item->id }}" method="POST" action="{{ $route('items.update', [$item]) }}">
                                @csrf
                                @method('PUT')
                            </form>
                            <input form="item-{{ $item->id }}" name="section" value="{{ $item->section }}" required maxlength="100" class="input" aria-label="Section of {{ $item->label }}">
                            <input form="item-{{ $item->id }}" name="label" value="{{ $item->label }}" required maxlength="255" class="input" aria-label="Item label">
                            <input form="item-{{ $item->id }}" type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" max="65535" required class="input" aria-label="Sort order of {{ $item->label }}">
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input form="item-{{ $item->id }}" type="checkbox" role="switch" name="is_required" value="1" class="input" @checked($item->is_required)> Required
                            </label>
                            <div class="flex gap-1">
                                <x-ui.button type="submit" form="item-{{ $item->id }}" variant="ghost" size="sm" icon="check">Save</x-ui.button>
                                <form method="POST" action="{{ $route('items.destroy', [$item]) }}" data-confirm="Delete this item?" data-confirm-button="Delete" data-confirm-text="{{ $item->label }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost" size="sm" icon="trash-2"><span class="sr-only">Delete {{ $item->label }}</span></x-ui.button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="space-y-5 pb-6">
                    @foreach ($items->groupBy('section') as $section => $sectionItems)
                        <div>
                            <h3 class="mb-2 text-sm font-semibold">{{ $section }}</h3>
                            <ul class="space-y-1 text-sm">
                                @foreach ($sectionItems as $item)
                                    <li class="flex items-center gap-2">
                                        {{ $item->label }}
                                        @unless ($item->is_required)
                                            <span class="badge" data-variant="outline">Optional</span>
                                        @endunless
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($editable)
                <x-slot:footer>
                    <form method="POST" action="{{ $route('items.store') }}" class="grid w-full gap-3 py-2 sm:grid-cols-[10rem_minmax(0,1fr)_5rem_auto_auto] sm:items-end">
                        @csrf
                        <x-ui.field label="Section" name="section" required>
                            <x-ui.input name="section" required maxlength="100" :value="$items->last()?->section" />
                        </x-ui.field>
                        <x-ui.field label="Item" name="label" required>
                            <x-ui.input name="label" required maxlength="255" />
                        </x-ui.field>
                        <x-ui.field label="Order" name="sort_order" required>
                            <x-ui.input type="number" name="sort_order" min="0" max="65535" required :value="($items->max('sort_order') ?? 0) + 10" />
                        </x-ui.field>
                        <div class="pb-2"><x-ui.switch name="is_required" label="Required" :checked="true" /></div>
                        <x-ui.button type="submit" icon="plus">Add item</x-ui.button>
                    </form>
                </x-slot:footer>
            @endif
        </x-ui.card>
    </div>
@endsection
