<x-ui.repeater-row title="Accident" :number="$number" :removable="$removable">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-ui.field label="Date" name="accident_date[{{ $i }}]">
            <x-ui.input type="date" name="accident_date[{{ $i }}]" :value="$values['date']" />
        </x-ui.field>
        <x-ui.field label="Location" name="accident_location[{{ $i }}]" class="lg:col-span-2">
            <x-ui.input name="accident_location[{{ $i }}]" :value="$values['location']" placeholder="City, state" />
        </x-ui.field>
        <x-ui.field label="Injuries" name="number_of_injuries[{{ $i }}]">
            <x-ui.input type="number" min="0" name="number_of_injuries[{{ $i }}]" :value="$values['injuries']" placeholder="0" />
        </x-ui.field>
        <x-ui.field label="Fatalities" name="number_of_fatalities[{{ $i }}]">
            <x-ui.input type="number" min="0" name="number_of_fatalities[{{ $i }}]" :value="$values['fatalities']" placeholder="0" />
        </x-ui.field>
    </div>
    <x-ui.radio-group name="hazmat_spill[{{ $i }}]" legend="Hazmat spill?" :value="$values['hazmat_spill']" />
</x-ui.repeater-row>
