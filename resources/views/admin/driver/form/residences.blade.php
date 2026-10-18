<x-ui.card id="section-residences" title="Residences, previous 3 years" description="List every address for the last 3 years if the driver has lived at the current address for less than 3 years. A residence with an address needs its city, state, country and postal code." class="scroll-mt-24">
    <div class="grid gap-4">
        <x-ui.checkbox name="same_address" id="same_address_checkbox" label="The first residence is the current address" description="Copies the current address into the first residence." />
        @include('admin.driver.form.repeater', ['group' => 'residences', 'rows' => $form->rows['residences'], 'row' => 'admin.driver.form.residence-row', 'addLabel' => 'Add residence'])
    </div>
</x-ui.card>
