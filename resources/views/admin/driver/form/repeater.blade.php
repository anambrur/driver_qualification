{{--
    Repeating rows posted as `name[index]`. The first row is never removed (it is the current
    residence / the first record); new rows take the next free index, so a removed row never
    shifts another row's values. Needs $group, $rows, $row (row partial), $addLabel.
--}}
@php($nextIndex = $rows === [] ? 0 : max(array_keys($rows)) + 1)
<div data-repeater="{{ $group }}" data-next-index="{{ $nextIndex }}" class="grid gap-4">
    <div data-repeater-rows class="grid gap-4">
        @foreach ($rows as $i => $values)
            @include($row, ['i' => $i, 'values' => $values, 'number' => $loop->iteration, 'removable' => ! $loop->first])
        @endforeach
    </div>
    <template data-repeater-template>
        @include($row, ['i' => '__INDEX__', 'values' => \App\Support\Driver\DriverForm::blank($group), 'number' => '__NUMBER__', 'removable' => true])
    </template>
    <div>
        <x-ui.button variant="outline" size="sm" icon="plus" data-repeater-add>{{ $addLabel }}</x-ui.button>
    </div>
</div>
