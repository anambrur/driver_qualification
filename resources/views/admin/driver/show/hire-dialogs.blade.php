{{-- Hire / Do-not-hire for a pending applicant, Terminate for a hired driver. Posts to admin.driver.hire-status (hire-script). --}}
<x-ui.dialog id="hire-dialog" title="Hire applicant" icon="user-round-check" size="md"
    :description="'You are hiring '.$driverFullName.'. This cannot be undone. Set the hire date and confirm to make the driver active.'">
    <form id="hireForm" class="grid gap-4">
        <x-ui.field label="Date hired" name="hire_date" required>
            <x-ui.input type="date" name="hire_date" :value="date('Y-m-d')" required />
        </x-ui.field>
        <x-ui.field label="Will the driver haul hazmat?" name="hazmat" required>
            <x-ui.select name="hazmat" placeholder="Please select" :options="['yes' => 'Yes', 'no' => 'No']" required />
        </x-ui.field>
        <x-ui.field label="Does the driver need a Longer Combination Vehicle (LCV) certificate?" name="lcv_certificate" required>
            <x-ui.select name="lcv_certificate" placeholder="Please select" :options="['yes' => 'Yes', 'no' => 'No']" required />
        </x-ui.field>
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
        <button type="submit" form="hireForm" class="btn">Confirm hire</button>
    </x-slot:footer>
</x-ui.dialog>

<x-ui.dialog id="not-hire-dialog" title="Do not hire" icon="user-round-x" size="md"
    description="Choose the reason and add any details, then confirm that the applicant will not be hired.">
    <form id="notHireForm" class="grid gap-4">
        <x-ui.field label="Reason" name="rejection_reason" required>
            <x-ui.select name="rejection_reason" placeholder="Please select a reason" required :options="[
                'not_good_fit' => 'Applicant is not a good fit for our company',
                'failed_drug_test' => 'Applicant failed a pre-employment drug test',
                'background_check_issues' => 'Items found on the background check',
                'cdl_issues' => 'Items found on the Commercial Driver’s License',
                'mvr_issues' => 'Items found on the Motor Vehicle Report (MVR)',
                'psp_issues' => 'Items found on the Pre-Employment Screening Program (PSP) report',
                'other' => 'Other reason not listed above',
            ]" />
        </x-ui.field>
        <x-ui.field label="Additional information" name="additional_info">
            <x-ui.textarea name="additional_info" rows="3" placeholder="Provide additional details…" />
        </x-ui.field>
        <x-ui.field label="Record date" name="record_date" required>
            <x-ui.input type="date" name="record_date" :value="date('Y-m-d')" required />
        </x-ui.field>
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
        <button type="submit" form="notHireForm" class="btn bg-destructive! text-white! hover:bg-destructive/90!">Confirm not hiring</button>
    </x-slot:footer>
</x-ui.dialog>

<x-ui.dialog id="terminate-dialog" title="Terminate driver" icon="user-round-minus" size="md"
    :description="'You are ending '.$driverFullName.'’s employment. Their login is switched off and their asset groups end. The DQ file is kept for 3 years (49 CFR 391.51). This cannot be undone.'">
    <form id="terminateForm" class="grid gap-4">
        <x-ui.field label="Termination date" name="termination_date" required>
            <x-ui.input type="date" name="termination_date" :value="date('Y-m-d')" :min="$driver->hire_date?->toDateString()" :max="date('Y-m-d')" required />
        </x-ui.field>
        <x-ui.field label="Reason" name="termination_reason" hint="Optional. Kept with the driver record.">
            <x-ui.textarea name="termination_reason" rows="3" maxlength="500" />
        </x-ui.field>
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
        <button type="submit" form="terminateForm" class="btn bg-destructive! text-white! hover:bg-destructive/90!">Terminate driver</button>
    </x-slot:footer>
</x-ui.dialog>
