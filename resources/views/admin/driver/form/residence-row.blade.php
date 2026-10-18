<x-ui.repeater-row title="Residence" :number="$number" :removable="$removable">
    <x-ui.field label="Street address" name="residence_address[{{ $i }}]">
        <x-ui.input name="residence_address[{{ $i }}]" :value="$values['address']" autocomplete="off" />
    </x-ui.field>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="City" name="residence_city[{{ $i }}]">
            <x-ui.input name="residence_city[{{ $i }}]" :value="$values['city']" />
        </x-ui.field>
        <x-ui.field label="Country" name="residence_country[{{ $i }}]">
            <x-ui.select name="residence_country[{{ $i }}]" :options="$form->countries" :value="$values['country']" placeholder="Select country" />
        </x-ui.field>
        <x-ui.field label="State" name="residence_state[{{ $i }}]">
            <x-ui.select name="residence_state[{{ $i }}]" :options="$form->states" :value="$values['state']" placeholder="Select state" />
        </x-ui.field>
        <x-ui.field label="Postal code" name="residence_postal_code[{{ $i }}]">
            <x-ui.input name="residence_postal_code[{{ $i }}]" :value="$values['postal_code']" />
        </x-ui.field>
    </div>
</x-ui.repeater-row>
