@extends('layouts.platform')

@section('title', 'Inspection templates')

@section('content')
    <x-ui.page-header title="Inspection templates"
        description="Pre-trip checklists that companies fill in before a trip starts. A version an inspection used is never edited: create a new version to change it. One version is active at a time." />

    <div class="space-y-6">
        <x-ui.card title="New template" description="Starts as a draft, version 1. Add its items, then publish it.">
            <form method="POST" action="{{ route('platform.lookups.inspection-templates.store') }}" class="grid gap-3 pb-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                @csrf
                <x-ui.field label="Name" name="name" required>
                    <x-ui.input name="name" required maxlength="100" />
                </x-ui.field>
                <x-ui.button type="submit" icon="plus">Create draft</x-ui.button>
            </form>
        </x-ui.card>

        @if ($templates->isEmpty())
            <x-ui.empty-state icon="clipboard-check" title="No templates yet" description="Create a draft and publish it so companies can record pre-trip inspections." />
        @else
            <x-ui.card flush class="py-0!">
                <x-ui.table>
                    <thead>
                        <tr><th>Template</th><th>Status</th><th>Items</th><th>Used</th><th>Published</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $template)
                            <tr>
                                <td data-label="Template" data-primary>
                                    @include('platform.partials.cell', ['title' => $template->label(), 'href' => route('platform.lookups.inspection-templates.edit', $template), 'sub' => \App\Models\InspectionTemplate::TYPES[$template->inspection_type]])
                                </td>
                                <td data-label="Status">
                                    @include('partials.ui.status-badge', ['status' => $template->isDraft() ? 'draft' : ($template->is_active ? 'active' : 'inactive')])
                                </td>
                                <td data-label="Items" class="tabular-nums">{{ $template->items_count }}</td>
                                <td data-label="Used">{{ $template->is_used ? 'Yes' : 'No' }}</td>
                                <td data-label="Published">{{ $template->published_at?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif
    </div>
@endsection
