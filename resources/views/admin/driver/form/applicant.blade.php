<x-ui.card id="section-applicant" title="Applicant information" class="scroll-mt-24">
    <div class="grid gap-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field label="First name" name="first_name" required>
                <x-ui.input name="first_name" :value="$v['first_name']" required autocomplete="given-name" />
            </x-ui.field>
            <x-ui.field label="Middle name" name="middle_name">
                <x-ui.input name="middle_name" :value="$v['middle_name']" autocomplete="additional-name" />
            </x-ui.field>
            <x-ui.field label="Last name" name="last_name" required>
                <x-ui.input name="last_name" :value="$v['last_name']" required autocomplete="family-name" />
            </x-ui.field>
            <x-ui.field label="Suffix" name="suffix">
                <x-ui.input name="suffix" :value="$v['suffix']" maxlength="10" autocomplete="honorific-suffix" />
            </x-ui.field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Date of birth" name="date_of_birth" required hint="The driver must be at least 18.">
                <x-ui.input type="date" name="date_of_birth" :value="$v['date_of_birth']" required autocomplete="bday" />
            </x-ui.field>
            <x-ui.field label="Social Security number" name="ssn" required>
                <x-ui.input name="ssn" :value="$v['ssn']" required placeholder="XXX-XX-XXXX" inputmode="numeric" autocomplete="off" />
            </x-ui.field>
            <x-ui.field label="Main phone" name="main_phone" required>
                <x-ui.input type="tel" name="main_phone" :value="$v['main_phone']" required autocomplete="tel" />
            </x-ui.field>
            <x-ui.field label="Alternate phone" name="alt_phone">
                <x-ui.input type="tel" name="alt_phone" :value="$v['alt_phone']" autocomplete="tel" />
            </x-ui.field>
            <x-ui.field label="Email" name="email" required class="sm:col-span-2">
                <x-ui.input type="email" name="email" :value="$v['email']" required autocomplete="email" />
            </x-ui.field>
        </div>

        <fieldset class="grid gap-4 border-t border-border pt-6 *:[legend]:float-left *:[legend]:w-full">
            <legend class="mb-4 text-sm font-medium">Current address</legend>
            <x-ui.field label="Street address" name="address" required>
                <x-ui.input name="address" :value="$v['address']" required autocomplete="street-address" />
            </x-ui.field>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="City" name="city" required>
                    <x-ui.input name="city" :value="$v['city']" required autocomplete="address-level2" />
                </x-ui.field>
                <x-ui.field label="Country" name="country" required>
                    <x-ui.select name="country" :options="$form->countries" :value="$v['country']" placeholder="Select country" required autocomplete="country" />
                </x-ui.field>
                <x-ui.field label="State" name="state" required>
                    <x-ui.select name="state" :options="$form->states" :value="$v['state']" placeholder="Select state" required autocomplete="address-level1" />
                </x-ui.field>
                <x-ui.field label="Postal code" name="postal_code" required>
                    <x-ui.input name="postal_code" :value="$v['postal_code']" required autocomplete="postal-code" />
                </x-ui.field>
            </div>
        </fieldset>

        <fieldset class="grid gap-4 border-t border-border pt-6 *:[legend]:float-left *:[legend]:w-full">
            <legend class="mb-4 text-sm font-medium">Business <span class="font-normal text-muted-foreground">(owner-operators)</span></legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Business name" name="business_name">
                    <x-ui.input name="business_name" :value="$v['business_name']" autocomplete="organization" />
                </x-ui.field>
                <x-ui.field label="Employer identification number" name="employer_identification_number">
                    <x-ui.input name="employer_identification_number" :value="$v['employer_identification_number']" maxlength="20" />
                </x-ui.field>
            </div>
            <x-ui.radio-group name="federal_tax_classification" legend="Federal tax classification" :options="\App\Support\Driver\DriverForm::TAX_CLASSIFICATIONS" :value="$v['federal_tax_classification']" />
        </fieldset>

        <div class="grid gap-3 border-t border-border pt-6 sm:grid-cols-2">
            <x-ui.checkbox name="twic_card" label="The driver has a TWIC card" :checked="$v['twic_card']" />
            <x-ui.checkbox name="passport" label="The driver has a passport" :checked="$v['passport']" />
        </div>

        <div class="border-t border-border pt-6">
            <x-driver-photo-upload :existing-photo-url="$driver?->photo_file_id ? route('admin.driver.file', [$driver->id, 'photo']) : null" />
        </div>
    </div>
</x-ui.card>
