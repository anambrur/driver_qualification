@php
    $licenseRequired = ! $driver;
    $licenseClasses = array_combine(\App\Support\Driver\DriverForm::LICENSE_CLASSES, \App\Support\Driver\DriverForm::LICENSE_CLASSES);
    if ($v['license_class'] && ! isset($licenseClasses[$v['license_class']])) {
        $licenseClasses[$v['license_class']] = $v['license_class'];
    }
@endphp
<x-ui.card id="section-license" title="Driver’s license" description="The current license. Enter the first and last name exactly as they appear on it." class="scroll-mt-24">
    <div class="grid gap-6">
        <x-ui.alert tone="warning" title="Check the license details carefully">
            A wrong license number means every document tied to the license has to be discarded and completed again, and may get the application rejected.
        </x-ui.alert>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field label="First name on license" name="license_first_name" :required="$licenseRequired">
                <x-ui.input name="license_first_name" :value="$v['license_first_name']" :required="$licenseRequired" />
            </x-ui.field>
            <x-ui.field label="Last name on license" name="license_last_name" :required="$licenseRequired">
                <x-ui.input name="license_last_name" :value="$v['license_last_name']" :required="$licenseRequired" />
            </x-ui.field>
            <x-ui.field label="Issued" name="license_issued" :required="$licenseRequired">
                <x-ui.input type="date" name="license_issued" :value="$v['license_issued']" :required="$licenseRequired" />
            </x-ui.field>
            <x-ui.field label="Expires" name="license_expires" :required="$licenseRequired">
                <x-ui.input type="date" name="license_expires" :value="$v['license_expires']" :required="$licenseRequired" />
            </x-ui.field>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Country" name="license_country" :required="$licenseRequired">
                <x-ui.select name="license_country" :options="$form->countries" :value="$v['license_country']" placeholder="Select country" :required="$licenseRequired" />
            </x-ui.field>
            <x-ui.field label="State" name="license_state" :required="$licenseRequired">
                <x-ui.select name="license_state" :options="$form->states" :value="$v['license_state']" placeholder="Select state" :required="$licenseRequired" />
            </x-ui.field>
            <x-ui.field label="Class" name="license_class" :required="$licenseRequired">
                <x-ui.select name="license_class" :options="$licenseClasses" :value="$v['license_class']" placeholder="Select class" :required="$licenseRequired" />
            </x-ui.field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="License number" name="license_number" :required="$licenseRequired">
                <x-ui.input name="license_number" :value="$v['license_number']" :required="$licenseRequired" autocomplete="off" spellcheck="false" />
            </x-ui.field>
            <x-ui.field label="Repeat license number" name="repeat_license_number" :required="$licenseRequired">
                <x-ui.input name="repeat_license_number" :value="$v['license_number']" :required="$licenseRequired" autocomplete="off" spellcheck="false" />
            </x-ui.field>
        </div>

        <fieldset class="grid gap-3">
            <legend class="label mb-3">Endorsements</legend>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (\App\Support\Driver\DriverForm::ENDORSEMENTS as $endorsement => $label)
                    <x-ui.checkbox :name="$endorsement" :label="$label" :checked="$v[$endorsement]" />
                @endforeach
            </div>
        </fieldset>
    </div>
</x-ui.card>
