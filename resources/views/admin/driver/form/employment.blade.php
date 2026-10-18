<x-ui.card id="section-employment" title="Employment history" description="Every employer for the previous 3 years, plus 7 more years for any job as a driver." class="scroll-mt-24">
    @include('admin.driver.form.repeater', ['group' => 'employment', 'rows' => $form->rows['employment'], 'row' => 'admin.driver.form.employment-row', 'addLabel' => 'Add employer'])
</x-ui.card>
