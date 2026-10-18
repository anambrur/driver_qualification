<x-ui.repeater-row title="Employer" :number="$number" :removable="$removable">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.field label="Employer name" name="employer_name[{{ $i }}]">
            <x-ui.input name="employer_name[{{ $i }}]" :value="$values['employer_name']" autocomplete="off" />
        </x-ui.field>
        <x-ui.field label="Position held" name="employer_record_position[{{ $i }}]">
            <x-ui.input name="employer_record_position[{{ $i }}]" :value="$values['position']" />
        </x-ui.field>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.field label="Street address" name="employer_record_address[{{ $i }}]">
            <x-ui.input name="employer_record_address[{{ $i }}]" :value="$values['address']" />
        </x-ui.field>
        <x-ui.field label="City" name="employer_record_city[{{ $i }}]">
            <x-ui.input name="employer_record_city[{{ $i }}]" :value="$values['city']" />
        </x-ui.field>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.field label="Country" name="employer_record_country[{{ $i }}]">
            <x-ui.select name="employer_record_country[{{ $i }}]" :options="$form->countries" :value="$values['country']" placeholder="Select country" />
        </x-ui.field>
        <x-ui.field label="State" name="employer_record_state[{{ $i }}]">
            <x-ui.select name="employer_record_state[{{ $i }}]" :options="$form->states" :value="$values['state']" placeholder="Select state" />
        </x-ui.field>
        <x-ui.field label="Postal code" name="employer_record_postal_code[{{ $i }}]">
            <x-ui.input name="employer_record_postal_code[{{ $i }}]" :value="$values['postal_code']" />
        </x-ui.field>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.field label="Phone" name="employer_record_phone[{{ $i }}]">
            <x-ui.input type="tel" name="employer_record_phone[{{ $i }}]" :value="$values['phone']" />
        </x-ui.field>
        <x-ui.field label="Fax" name="employer_record_fax[{{ $i }}]">
            <x-ui.input type="tel" name="employer_record_fax[{{ $i }}]" :value="$values['fax']" />
        </x-ui.field>
        <x-ui.field label="Email" name="employer_record_email[{{ $i }}]">
            <x-ui.input type="email" name="employer_record_email[{{ $i }}]" :value="$values['email']" />
        </x-ui.field>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.field label="From" name="employer_record_date_from[{{ $i }}]">
            <x-ui.input type="date" name="employer_record_date_from[{{ $i }}]" :value="$values['date_from']" />
        </x-ui.field>
        <x-ui.field label="To" name="employer_record_date_to[{{ $i }}]">
            <x-ui.input type="date" name="employer_record_date_to[{{ $i }}]" :value="$values['date_to']" />
        </x-ui.field>
        <x-ui.field label="Reason for leaving" name="employer_record_reason_for_leaving[{{ $i }}]">
            <x-ui.input name="employer_record_reason_for_leaving[{{ $i }}]" :value="$values['reason_for_leaving']" />
        </x-ui.field>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.radio-group name="employed_regulations[{{ $i }}]" legend="Was the driver subject to the DOT/FMCSA regulations while employed by this carrier?" :value="$values['employed_regulations']" />
        <x-ui.radio-group name="safety_sensitive_function[{{ $i }}]" legend="Was the job a DOT safety-sensitive function?" :value="$values['safety_sensitive_function']" />
    </div>
</x-ui.repeater-row>
