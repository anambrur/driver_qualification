{{--
    A pre-trip inspection (req 5.1–5.3) on one screen, grouped by section: pass / fail / n/a and a
    note per item, saved as each answer is given; photos or files on failed items go into the trip
    folder. Read-only once completed or abandoned, or without inspections.manage.
--}}
@extends('layouts.main-layout')

@section('title', 'Pre-trip inspection · Trip '.$inspection->trip->label())

@section('content')
    @php
        $trip = $inspection->trip;
        $driverName = $inspection->driver ? trim($inspection->driver->first_name.' '.$inspection->driver->last_name) : '—';
    @endphp
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Pre-trip inspection" :description="$inspection->template->label().' · a pre-trip checklist, not a DVIR'">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Trips', 'href' => route('trips.index')], ['label' => $trip->label(), 'href' => route('trips.show', $trip).'#inspection'], ['label' => 'Inspection']]" />
            </x-slot:breadcrumbs>
            <x-slot:actions>
                @if ($canAbandon)
                    <form method="POST" action="{{ route('inspections.abandon', $inspection) }}"
                        data-confirm="Abandon this inspection?" data-confirm-text="Its answers are kept but it no longer counts. Start a new inspection to start the trip." data-confirm-button="Abandon">
                        @csrf
                        <x-ui.button type="submit" variant="outline" icon="circle-x">Abandon</x-ui.button>
                    </form>
                @endif
                <x-ui.button :href="route('trips.show', $trip).'#inspection'" variant="outline" icon="arrow-left">Back to trip</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-6">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif
        @if ($crewChanged)
            <x-ui.alert tone="warning" class="mb-6">The trip's driver or equipment changed since this inspection started. Abandon it and start a new one from the trip.</x-ui.alert>
        @endif

        <div class="max-w-4xl space-y-6">
            <x-ui.card>
                <div class="space-y-4 py-6">
                    <div class="flex flex-wrap items-center gap-2">
                        @include('partials.ui.status-badge', ['status' => $inspection->status])
                        @if ($inspection->result)
                            <span class="badge" data-tone="{{ $inspection->result === 'no_defects' ? 'success' : 'danger' }}">{{ \App\Models\Inspection::RESULTS[$inspection->result] }}</span>
                        @endif
                    </div>
                    <dl class="grid gap-3 text-sm sm:grid-cols-4">
                        <div><dt class="text-muted-foreground">Driver</dt><dd class="font-medium">{{ $driverName }}</dd></div>
                        <div><dt class="text-muted-foreground">Tractor</dt><dd class="font-medium">{{ $inspection->vehicle?->unit_no ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Trailer</dt><dd class="font-medium">{{ $inspection->trailer?->unit_no ?? 'None' }}</dd></div>
                        <div><dt class="text-muted-foreground">Started</dt><dd>{{ $inspection->started_at?->format('M d, Y g:i A') }}</dd></div>
                        @unless ($inspection->isOpen())
                            <div><dt class="text-muted-foreground">Odometer</dt><dd class="tabular-nums">{{ $inspection->odometer !== null ? number_format($inspection->odometer) : '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">By</dt><dd>{{ $inspection->performedBy?->name ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Completed</dt><dd>{{ $inspection->completed_at?->format('M d, Y g:i A') ?? '—' }}</dd></div>
                            <div class="sm:col-span-4"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ $inspection->notes ?: '—' }}</dd></div>
                        @endunless
                    </dl>
                    @include('inspections.partials.progress', ['answered' => (int) $inspection->results_count, 'total' => (int) $inspection->items_count])
                </div>
            </x-ui.card>

            @if ($canRecord)
                <form id="inspection-form" method="POST" action="{{ route('inspections.results.update', $inspection) }}" data-inspection-autosave>
                    @csrf
                    @method('PUT')
                </form>
            @endif

            @foreach ($inspection->template->items->groupBy('section') as $section => $items)
                <x-ui.card :title="$section">
                    <ul class="divide-y divide-border pb-2">
                        @foreach ($items as $item)
                            @php
                                $result = $results->get($item->id);
                                // The wording the item was answered with (the result's snapshot)
                                $label = $result?->item_label ?? $item->label;
                                $files = $attachments->get(\App\Services\Operations\InspectionService::attachmentTitle($item->section, $label), collect());
                            @endphp
                            <li id="item-{{ $item->id }}" class="space-y-3 py-4" data-item="{{ $item->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <p class="font-medium">
                                        {{ $label }}
                                        @if ($item->is_required)
                                            <span class="text-destructive" aria-hidden="true">*</span><span class="sr-only">(required)</span>
                                        @endif
                                    </p>
                                    @if ($canRecord)
                                        <fieldset class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Result for {{ $label }}">
                                            @foreach (\App\Models\InspectionResult::RESULTS as $value => $option)
                                                <label class="label inline-flex items-center gap-2 font-normal">
                                                    <input type="radio" form="inspection-form" name="results[{{ $item->id }}][result]" value="{{ $value }}" class="input" @checked($result?->result === $value)>
                                                    {{ $option }}
                                                </label>
                                            @endforeach
                                        </fieldset>
                                    @elseif ($result)
                                        <span class="badge" data-tone="{{ ['pass' => 'success', 'fail' => 'danger', 'na' => 'neutral'][$result->result] }}">{{ \App\Models\InspectionResult::RESULTS[$result->result] }}</span>
                                    @else
                                        <span class="text-sm text-muted-foreground">Not answered</span>
                                    @endif
                                </div>
                                @if ($canRecord)
                                    <div class="flex items-center gap-3">
                                        <input type="text" form="inspection-form" name="results[{{ $item->id }}][note]" value="{{ $result?->note }}" maxlength="1000" class="input w-full" placeholder="Note (optional)" aria-label="Note for {{ $label }}">
                                        <span class="w-14 shrink-0 text-xs text-muted-foreground" data-save-state aria-live="polite"></span>
                                    </div>
                                @elseif ($result?->note)
                                    <p class="text-sm text-muted-foreground">{{ $result->note }}</p>
                                @endif

                                @if ($files->isNotEmpty())
                                    <ul class="flex flex-wrap gap-2 text-sm">
                                        @foreach ($files as $document)
                                            <li>
                                                <a href="{{ route('inspections.attachments.show', [$inspection, $document]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 underline underline-offset-2">
                                                    {{ svg('lucide-paperclip', 'size-3.5', ['aria-hidden' => 'true']) }}{{ $document->file?->original_name ?? $document->title }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($canRecord)
                                    <form method="POST" action="{{ route('inspections.attachments.store', $inspection) }}" enctype="multipart/form-data"
                                        class="flex flex-wrap items-center gap-2" data-fail-only @if ($result?->result !== 'fail') hidden @endif>
                                        @csrf
                                        <input type="hidden" name="template_item_id" value="{{ $item->id }}">
                                        <input type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt" class="input max-w-xs text-sm" aria-label="Photos or files for {{ $label }}">
                                        <x-ui.button type="submit" variant="outline" size="sm" icon="camera">Add to trip folder</x-ui.button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach

            @if ($canRecord)
                <div class="flex justify-end">
                    <x-ui.button type="submit" form="inspection-form" variant="outline" icon="save">Save answers</x-ui.button>
                </div>
                <x-ui.card title="Complete" description="Every required item (*) must be answered. Any failed item means defects found: the trip can't start until a new inspection finds none.">
                    <form method="POST" action="{{ route('inspections.complete', $inspection) }}" class="grid gap-4 pb-6 sm:grid-cols-[12rem_minmax(0,1fr)]"
                        data-confirm="Complete this inspection?" data-confirm-text="The answers can't change afterwards." data-confirm-button="Complete">
                        @csrf
                        <x-ui.field label="Odometer" name="odometer" hint="Optional, in miles.">
                            <x-ui.input type="number" name="odometer" min="0" max="{{ \App\Models\Inspection::MAX_ODOMETER }}" step="1" />
                        </x-ui.field>
                        <x-ui.field label="Notes" name="notes">
                            <x-ui.textarea name="notes" rows="2" maxlength="2000" />
                        </x-ui.field>
                        <div class="flex justify-end sm:col-span-2">
                            <x-ui.button type="submit" icon="circle-check">Complete inspection</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection

@if ($canRecord)
    @push('scripts')
        <script>
            // Saves each answer as it is given (req 5.2); the Save answers button is the fallback.
            (() => {
                const form = document.querySelector('[data-inspection-autosave]');
                if (!form) return;
                const token = form.querySelector('input[name="_token"]').value;
                const progress = document.querySelector('[data-inspection-progress]');

                // One request at a time per item, so answers reach the server in the order given
                const queue = new WeakMap();
                const save = (item) => queue.set(item, (queue.get(item) ?? Promise.resolve()).then(() => send(item)));
                const send = async (item) => {
                    const id = item.dataset.item;
                    const checked = item.querySelector(`input[name="results[${id}][result]"]:checked`);
                    if (!checked) return;
                    const state = item.querySelector('[data-save-state]');
                    const note = item.querySelector(`input[name="results[${id}][note]"]`).value;
                    state.textContent = 'Saving…';
                    try {
                        const response = await fetch(form.action, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ results: { [id]: { result: checked.value, note } } }),
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Not saved');
                        state.textContent = 'Saved';
                        progress.querySelector('[data-answered]').textContent = data.answered;
                        progress.querySelector('[data-percent]').textContent = data.progress;
                        progress.querySelector('[role="progressbar"]').setAttribute('aria-valuenow', data.progress);
                        progress.querySelector('[data-bar]').style.width = `${data.progress}%`;
                    } catch (error) {
                        state.textContent = 'Not saved';
                        window.notify?.('error', 'Answer not saved', error.message);
                    }
                };

                document.querySelectorAll('[data-item]').forEach((item) => {
                    item.addEventListener('change', (event) => {
                        if (event.target.type === 'file') return;
                        if (event.target.type === 'radio') {
                            item.querySelector('[data-fail-only]')?.toggleAttribute('hidden', event.target.value !== 'fail');
                        }
                        save(item);
                    });
                });
            })();
        </script>
    @endpush
@endif
