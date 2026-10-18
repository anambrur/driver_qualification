<x-ui.repeater-row title="Violation" :number="$number" :removable="$removable">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="Date" name="violation_date[{{ $i }}]">
            <x-ui.input type="date" name="violation_date[{{ $i }}]" :value="$values['date']" />
        </x-ui.field>
        <x-ui.field label="Location" name="violation_location[{{ $i }}]">
            <x-ui.input name="violation_location[{{ $i }}]" :value="$values['location']" placeholder="City, state" />
        </x-ui.field>
        <x-ui.field label="Offense" name="offense[{{ $i }}]">
            <x-ui.input name="offense[{{ $i }}]" :value="$values['offense']" />
        </x-ui.field>
        <x-ui.field label="Vehicle type" name="vehicle_type[{{ $i }}]">
            <x-ui.input name="vehicle_type[{{ $i }}]" :value="$values['vehicle_type']" />
        </x-ui.field>
    </div>
</x-ui.repeater-row>
