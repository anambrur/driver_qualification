{{--
    An add/remove list of rows posted as $name[i][key]: $name, $rows, $blank (a new row), $max,
    $label (one row), $empty, and $fields: [key => ['label' => .., 'type' => input|textarea|select, 'options' => [..], 'wide' => bool]].
--}}
@php
    $errorKey = \App\Support\Ui\Field::errorKey($name);
    $rowErrors = collect($errors->get($errorKey.'.*'))->flatten()->merge($errors->get($errorKey))->unique();
    $id = \App\Support\Ui\Field::id($name);
@endphp
<div x-data="{ rows: @js(array_values($rows)), max: {{ $max }}, blank: @js($blank) }" class="grid gap-3">
    <template x-for="(row, i) in rows" :key="i">
        <div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2">
            <div class="flex items-center justify-between gap-3 sm:col-span-2">
                <h3 class="text-sm font-medium">{{ $label }} <span x-text="i + 1"></span></h3>
                <x-ui.button variant="ghost" size="sm" icon="trash-2" class="text-destructive" x-on:click="rows.splice(i, 1)">Remove</x-ui.button>
            </div>
            @foreach ($fields as $key => $field)
                <div @class(['field gap-2', 'sm:col-span-2' => $field['wide'] ?? false])>
                    <label class="label" x-bind:for="`{{ $id }}-${i}-{{ $key }}`">{{ $field['label'] }}</label>
                    @switch($field['type'] ?? 'input')
                        @case('select')
                            <select class="select w-full" x-bind:id="`{{ $id }}-${i}-{{ $key }}`" x-bind:name="`{{ $name }}[${i}][{{ $key }}]`" x-model="row.{{ $key }}">
                                @foreach ($field['options'] as $value => $text)
                                    <option value="{{ $value }}">{{ $text }}</option>
                                @endforeach
                            </select>
                            @break
                        @case('textarea')
                            <textarea class="textarea" rows="2" x-bind:id="`{{ $id }}-${i}-{{ $key }}`" x-bind:name="`{{ $name }}[${i}][{{ $key }}]`" x-model="row.{{ $key }}"
                                @isset($field['max']) maxlength="{{ $field['max'] }}" @endisset></textarea>
                            @break
                        @default
                            <input type="text" class="input w-full" x-bind:id="`{{ $id }}-${i}-{{ $key }}`" x-bind:name="`{{ $name }}[${i}][{{ $key }}]`" x-model="row.{{ $key }}"
                                @isset($field['max']) maxlength="{{ $field['max'] }}" @endisset>
                    @endswitch
                </div>
            @endforeach
        </div>
    </template>
    <p x-show="rows.length === 0" class="text-sm text-muted-foreground">{{ $empty }}</p>
    @if ($rowErrors->isNotEmpty())
        <div role="alert" class="text-sm text-destructive">
            @foreach ($rowErrors as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif
    <div class="flex items-center gap-3">
        <x-ui.button variant="outline" size="sm" icon="plus" x-on:click="rows.push({ ...blank })" x-bind:disabled="rows.length >= max">Add {{ \Illuminate\Support\Str::lower($label) }}</x-ui.button>
        <span class="text-xs text-muted-foreground" x-text="`${rows.length} of ${max}`"></span>
    </div>
</div>
