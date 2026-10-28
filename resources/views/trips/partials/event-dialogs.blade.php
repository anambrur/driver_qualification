{{--
    The dialogs of the Events tab (req 7.1–7.6): one for the five event types (each button presets
    the type; only that type's fields are shown and posted), one for a fuel stop, and the shared
    "Add files" and "Void" dialogs. A refused save reopens its dialog with the values typed in.
    Needs $trip, $eventDefaultAt and $fuelTypes.
--}}
@php
    $service = \App\Services\Operations\TripEventService::class;
    $eventTypes = array_diff_key(\App\Models\TripEvent::TYPES, ['fuel' => true]);
    $accept = '.'.implode(',.', \App\Http\Requests\Documents\UploadDocumentsRequest::TYPES);
    $place = ['address_line1' => 'address', 'address_line2' => null, 'zip' => null, 'country_code' => null, 'place_source' => null, 'place_ref' => null];
    // The fuel form's picker shares the page, so its fields are prefixed (FuelStopRequest::placePrefix)
    $fuelPlace = array_merge($place, ['address_line1' => 'fuel_address', 'city' => 'fuel_city', 'state_code' => 'fuel_state_code', 'latitude' => 'fuel_latitude', 'longitude' => 'fuel_longitude']);
    // Errors of single files (files.photos.0, receipts.1) have no field of their own
    $fileErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => $key === 'files' || str_starts_with($key, 'files.') || str_starts_with($key, 'receipts'))->flatten()->unique()->values();
    $slotInput = fn (string $slot, string $hint = '') => ['slot' => $slot, 'label' => $service::SLOTS[$slot][1], 'hint' => $hint];
    $eventSlots = [
        'police_stop' => [$slotInput('ticket', 'Required when a ticket was issued.')],
        'weigh_station' => [$slotInput('weight', 'Required when the weight is not OK, or use Other files.')],
        'accident' => [$slotInput('licence'), $slotInput('insurance'), $slotInput('photos', 'Both vehicles. At least one file is required for an accident.')],
    ];
@endphp

<x-ui.dialog id="trip-event-dialog" title="Record an event" :description="'Trip '.$trip->label().'. Times in '.str_replace('_', ' ', $timezone ?? '').'.'" icon="list-plus" size="lg">
    <form id="trip-event-form" method="POST" action="{{ route('trips.events.store', $trip) }}" enctype="multipart/form-data" class="grid gap-4" data-event-form>
        @csrf
        <input type="hidden" name="_form" value="event">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Event" name="type" required>
                <x-ui.select name="type" :options="$eventTypes" :value="old('type', 'stop')" required data-event-type-select />
            </x-ui.field>
            <x-ui.field label="When" name="occurred_at" required>
                <x-ui.input type="datetime-local" name="occurred_at" :value="old('_form') === 'event' ? old('occurred_at') : $eventDefaultAt" required />
            </x-ui.field>
        </div>

        <div data-for-types="police_stop" hidden>
            <x-ui.radio-group name="ticket_issued" legend="Ticket issued?" :options="['1' => 'Yes', '0' => 'No']" required />
        </div>
        <div data-for-types="weigh_station" hidden>
            <x-ui.radio-group name="weight_ok" legend="Weight OK?" :options="['1' => 'Yes', '0' => 'No']" required />
        </div>
        <div data-for-types="accident" hidden class="grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Injuries" name="injuries_count" required>
                <x-ui.input type="number" name="injuries_count" min="0" max="999" :value="old('injuries_count', 0)" required />
            </x-ui.field>
            <x-ui.field label="Fatalities" name="fatalities_count" required>
                <x-ui.input type="number" name="fatalities_count" min="0" max="999" :value="old('fatalities_count', 0)" required />
            </x-ui.field>
            <x-ui.radio-group name="hazmat_released" legend="Hazmat released?" :options="['1' => 'Yes', '0' => 'No']" :value="old('hazmat_released', '0')" required />
        </div>

        <x-ui.field label="Reason or notes" name="reason" hint="Required for a stop, and for a police stop without a ticket.">
            <x-ui.textarea name="reason" rows="3" maxlength="2000">{{ old('reason') }}</x-ui.textarea>
        </x-ui.field>

        @if (old('_form') === 'event' && $fileErrors->isNotEmpty())
            <x-ui.alert tone="danger">{{ $fileErrors->implode(' ') }}</x-ui.alert>
        @endif
        @foreach ($eventSlots as $type => $slots)
            @foreach ($slots as $input)
                <div data-for-types="{{ $type }}" hidden>
                    <x-ui.field :label="$input['label']" :name="'files.'.$input['slot']" :hint="$input['hint'] ?: null">
                        <input type="file" name="files[{{ $input['slot'] }}][]" multiple accept="{{ $accept }}" class="input text-sm">
                    </x-ui.field>
                </div>
            @endforeach
        @endforeach
        <x-ui.field label="Other files" name="files.other" hint="PDF, images, Word or Excel; up to 10 files of 20 MB. Photos become PDFs.">
            <input type="file" name="files[other][]" multiple accept="{{ $accept }}" class="input text-sm">
        </x-ui.field>

        <details @if (old('_form') === 'event' && (old('address') || old('city') || old('state_code'))) open @endif>
            <summary class="cursor-pointer text-sm font-medium">Location (optional)</summary>
            <x-maps.address-picker class="mt-4" :names="$place" optional />
        </details>
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
        <button type="submit" form="trip-event-form" class="btn">Record event</button>
    </x-slot:footer>
</x-ui.dialog>

<x-ui.dialog id="trip-fuel-dialog" title="Fuel stop" :description="'Trip '.$trip->label().'. Prices in USD. The stop is done once the receipt is uploaded.'" icon="fuel" size="lg">
    <form id="trip-fuel-form" method="POST" action="{{ route('trips.fuel-stops.store', $trip) }}" enctype="multipart/form-data" class="grid gap-4" data-fuel-form>
        @csrf
        <input type="hidden" name="_form" value="fuel">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="When" name="occurred_at" for="fuel_occurred_at" required>
                <x-ui.input type="datetime-local" name="occurred_at" id="fuel_occurred_at" :value="old('_form') === 'fuel' ? old('occurred_at') : $eventDefaultAt" required />
            </x-ui.field>
            <x-ui.field label="Station" name="location_name" hint="Optional, e.g. the truck stop's name.">
                <x-ui.input name="location_name" :value="old('location_name')" maxlength="255" />
            </x-ui.field>
            <x-ui.field label="Fuel type" name="fuel_type_id" required>
                <x-ui.select name="fuel_type_id" :options="$fuelTypes" :value="old('fuel_type_id')" placeholder="Select" required />
            </x-ui.field>
            <x-ui.field label="Odometer" name="odometer">
                <x-ui.input type="number" name="odometer" min="0" max="9999999" :value="old('odometer')" />
            </x-ui.field>
            <x-ui.field label="Gallons" name="gallons" required>
                <x-ui.input type="number" name="gallons" min="0.001" max="9999999.999" step="0.001" :value="old('gallons')" required data-fuel="gallons" />
            </x-ui.field>
            <x-ui.field label="Price per gallon ($)" name="price_per_gallon" required>
                <x-ui.input type="number" name="price_per_gallon" min="0.001" max="99999.999" step="0.001" :value="old('price_per_gallon')" required data-fuel="price" />
            </x-ui.field>
            <x-ui.field label="Total ($)" name="total_amount" required class="sm:col-span-2">
                <x-ui.input type="number" name="total_amount" min="0.01" step="0.01" :value="old('total_amount')" required data-fuel="total" />
            </x-ui.field>
        </div>
        @if (old('_form') === 'fuel' && $fileErrors->isNotEmpty())
            <x-ui.alert tone="danger">{{ $fileErrors->implode(' ') }}</x-ui.alert>
        @endif
        <p class="text-sm text-warning" data-fuel-warning hidden role="status">The total is not gallons × price per gallon. Check the receipt; you can still save.</p>
        <x-ui.field label="Receipt" name="receipts" hint="Photo or PDF of the receipt. Without it the stop stays open until you add it.">
            <input type="file" name="receipts[]" multiple accept="{{ $accept }}" class="input text-sm">
        </x-ui.field>
        <p class="text-sm font-medium">Location</p>
        <x-maps.address-picker :names="$fuelPlace" optional require-state />
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
        <button type="submit" form="trip-fuel-form" class="btn">Record fuel stop</button>
    </x-slot:footer>
</x-ui.dialog>

<x-ui.dialog id="trip-event-attach-dialog" title="Add files" description="The files go into the trip folder." icon="paperclip" size="sm">
    <form id="trip-event-attach-form" method="POST" action="" enctype="multipart/form-data" class="grid gap-4">
        @csrf
        <input type="hidden" name="_form" value="attach">
        <p class="text-sm text-muted-foreground" data-attach-title></p>
        <x-ui.field label="What are they?" name="slot" required>
            <select name="slot" class="select w-full" required data-attach-slot></select>
        </x-ui.field>
        <x-ui.field label="Files" name="files" required>
            <input type="file" name="files[]" multiple required accept="{{ $accept }}" class="input text-sm">
        </x-ui.field>
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
        <button type="submit" form="trip-event-attach-form" class="btn">Add files</button>
    </x-slot:footer>
</x-ui.dialog>

<x-ui.dialog id="trip-event-void-dialog" title="Void this event?" description="A voided event stays on the trip, struck through, with its files. It can't be undone." icon="ban" size="sm">
    <form id="trip-event-void-form" method="POST" action="" class="grid gap-4"
        data-confirm="Void this event?" data-confirm-text="It stays on the trip as evidence, struck through, and no longer counts." data-confirm-button="Void">
        @csrf
        <input type="hidden" name="_form" value="void">
        <p class="text-sm text-muted-foreground" data-void-title></p>
        <x-ui.field label="Reason" name="void_reason" required>
            <x-ui.textarea name="void_reason" rows="3" maxlength="500" required />
        </x-ui.field>
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-dialog-close>Keep event</button>
        <button type="submit" form="trip-event-void-form" class="btn" data-variant="destructive">Void event</button>
    </x-slot:footer>
</x-ui.dialog>

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-event-form]');
            const select = form?.querySelector('[data-event-type-select]');
            // Only the chosen type's fields are shown and posted
            const showType = (type) => {
                form.querySelectorAll('[data-for-types]').forEach((section) => {
                    const on = section.dataset.forTypes.split(' ').includes(type);
                    section.hidden = !on;
                    section.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !on; });
                });
            };
            if (select) {
                select.addEventListener('change', () => showType(select.value));
                showType(select.value);
            }

            const fuel = document.querySelector('[data-fuel-form]');
            const check = () => {
                const value = (name) => parseFloat(fuel.querySelector(`[data-fuel="${name}"]`).value);
                const [gallons, price, total] = [value('gallons'), value('price'), value('total')];
                const expected = gallons * price;
                fuel.querySelector('[data-fuel-warning]').hidden = !(expected > 0 && total > 0 && Math.abs(total - expected) > Math.max(expected, 0.01) * @json(\App\Models\FuelPurchase::TOTAL_TOLERANCE));
            };
            fuel?.addEventListener('input', (event) => { if (event.target.dataset.fuel) check(); });

            document.addEventListener('click', (event) => {
                const button = event.target instanceof Element ? event.target.closest('[data-event-type], [data-attach-action], [data-void-action]') : null;
                if (!button) return;
                if (button.dataset.eventType && select) {
                    select.value = button.dataset.eventType;
                    showType(select.value);
                } else if (button.dataset.attachAction) {
                    const attach = document.getElementById('trip-event-attach-form');
                    attach.action = button.dataset.attachAction;
                    attach.querySelector('[data-attach-title]').textContent = button.dataset.attachLabel;
                    const slots = attach.querySelector('[data-attach-slot]');
                    slots.replaceChildren(...Object.entries(JSON.parse(button.dataset.attachSlots)).map(([value, label]) => new Option(label, value)));
                } else if (button.dataset.voidAction) {
                    const voidForm = document.getElementById('trip-event-void-form');
                    voidForm.action = button.dataset.voidAction;
                    voidForm.querySelector('[data-void-title]').textContent = button.dataset.voidLabel;
                }
            });

            // A refused save reopens its dialog
            const reopen = @json($errors->any() ? ['event' => 'trip-event-dialog', 'fuel' => 'trip-fuel-dialog'][old('_form')] ?? null : null);
            if (reopen) document.addEventListener('DOMContentLoaded', () => document.getElementById(reopen)?.showModal());
        })();
    </script>
@endpush
