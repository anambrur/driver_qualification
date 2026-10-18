@extends('layouts.application-form-layout')

@section('title', 'Violation Record | DOT Driver Qualification')

@section('content')
    @php
        $violations = $driver->violations;
        $hasViolations = $violations && $violations->isNotEmpty() && $violations->first()->violation == 'yes';
        $signedDate = fn ($v) => $v->violation_record_date_signed ? \Carbon\Carbon::parse($v->violation_record_date_signed)->format('m/d/Y') : 'N/A';
    @endphp
    <x-application.step :company="$company" :driver="$driver" :step="5" title="Violation Record & Annual Review"
        description="List your traffic violations from the past 12 months and sign the authorization.">
        <form id="step-form" action="{{ route('public.application.store.step5', ['slug' => $company->slug]) }}" method="POST" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver->id }}">
            <input type="hidden" name="from_edit" value="0">

            <x-ui.card title="Traffic Violations Record (Past 12 Months)">
                <div class="grid gap-5">
                    <p class="text-sm font-medium">I certify that the following is a true and complete list of traffic violations (other than parking violations) for which I have been convicted or forfeited bond or collateral during the past 12 months.</p>

                    <fieldset class="flex flex-wrap gap-x-6 gap-y-2">
                        <legend class="sr-only">Violations in the past 12 months</legend>
                        <label class="label inline-flex items-center gap-2 font-normal">
                            <input type="radio" name="violation" value="no" class="input"
                                {{ $driverDocument && $driverDocument->violation_record_signature && (!$violations || $violations->isEmpty() || $violations->first()->violation == 'no') ? 'checked' : '' }} />
                            I have had no violations
                        </label>
                        <label class="label inline-flex items-center gap-2 font-normal">
                            <input type="radio" name="violation" value="yes" class="input" {{ $hasViolations ? 'checked' : '' }} />
                            I have had violations (list below)
                        </label>
                    </fieldset>

                    <div id="violation_fields">
                        <x-ui.table>
                            <thead>
                                <tr>
                                    <th>Date MM/DD/YYYY</th>
                                    <th>Location City/State</th>
                                    <th>Offense</th>
                                    <th>Vehicle Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($hasViolations)
                                    @foreach ($violations as $index => $violation)
                                        @if ($violation->violation == 'yes' && $violation->violation_date)
                                            <tr>
                                                <td data-label="Date"><input type="date" name="violation_date[]" value="{{ old('violation_date.' . $index, $violation->violation_date) }}" class="input w-full" aria-label="Violation date"></td>
                                                <td data-label="Location"><input type="text" name="violation_location[]" value="{{ old('violation_location.' . $index, $violation->violation_location) }}" class="input w-full" aria-label="Violation location"></td>
                                                <td data-label="Offense"><input type="text" name="offense[]" value="{{ old('offense.' . $index, $violation->offense) }}" placeholder="Enter offense" class="input w-full" aria-label="Offense"></td>
                                                <td data-label="Vehicle type"><input type="text" name="vehicle_type[]" value="{{ old('vehicle_type.' . $index, $violation->vehicle_type) }}" placeholder="Enter vehicle type" class="input w-full" aria-label="Vehicle type"></td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @else
                                    <tr>
                                        <td data-label="Date"><input type="date" name="violation_date[]" value="{{ old('violation_date.0') }}" class="input w-full" aria-label="Violation date"></td>
                                        <td data-label="Location"><input type="text" name="violation_location[]" value="{{ old('violation_location.0') }}" class="input w-full" aria-label="Violation location"></td>
                                        <td data-label="Offense"><input type="text" name="offense[]" value="{{ old('offense.0') }}" placeholder="Enter offense" class="input w-full" aria-label="Offense"></td>
                                        <td data-label="Vehicle type"><input type="text" name="vehicle_type[]" value="{{ old('vehicle_type.0') }}" placeholder="Enter vehicle type" class="input w-full" aria-label="Vehicle type"></td>
                                    </tr>
                                @endif
                            </tbody>
                        </x-ui.table>

                        <div class="mt-4 flex flex-wrap justify-between gap-3">
                            <x-ui.button id="violation_add" variant="secondary" icon="plus">Add Another Violation</x-ui.button>
                            <x-ui.button id="violation_remove" variant="outline" icon="minus" class="text-destructive hover:text-destructive">Remove Last Violation</x-ui.button>
                        </div>
                    </div>

                    <x-ui.alert>If no violations are listed above, I certify that I have not been convicted or forfeited bond or collateral on account of any violation required to be listed during the past 12 months.</x-ui.alert>
                </div>
            </x-ui.card>

            <x-ui.card title="Fair Credit Reporting Act Authorization">
                <div class="grid gap-6">
                    <div class="grid gap-3 text-sm">
                        <p>Pursuant to the federal Fair Credit Reporting Act, I hereby authorize this company and its designated agents and representatives to conduct a comprehensive review of my background through any consumer report for employment. I understand that the scope of the consumer report/investigative consumer report may include, but is not limited to, the following areas:</p>
                        <ul class="ml-4 list-disc space-y-1 pl-4 text-muted-foreground">
                            <li>verification of Social Security number</li>
                            <li>current and previous residences</li>
                            <li>employment history, including all personnel files</li>
                            <li>education</li>
                            <li>references</li>
                            <li>credit history and reports</li>
                            <li>criminal history, including records from any criminal justice agency in any or all federal, state or county jurisdictions</li>
                            <li>birth records</li>
                            <li>motor vehicle records, including traffic citations and registration</li>
                            <li>any other public records</li>
                        </ul>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.field label="Applicant Signature" name="applicant_signature" for="applicant_signature" required>
                            <x-ui.input id="applicant_signature" name="applicant_signature" :value="$driverDocument->violation_record_signature ?? ''" placeholder="Type your full name as signature" required />
                        </x-ui.field>
                        <x-ui.field label="Date Signed" name="date_signed" for="date_signed" required>
                            <x-ui.input type="date" id="date_signed" name="date_signed" :value="$driverDocument->violation_record_date_signed ?? date('Y-m-d')" required />
                        </x-ui.field>
                    </div>
                </div>
            </x-ui.card>

            @if ($violations && $violations->isNotEmpty())
                <x-ui.card title="Current Violation Information">
                    @if ($violations->first()->violation == 'no')
                        <x-ui.alert tone="success" title="No violations recorded for this driver.">
                            <p><strong>Signature:</strong> {{ $violations->first()->violation_record_signature ?? 'N/A' }}</p>
                            <p><strong>Date Signed:</strong> {{ $signedDate($violations->first()) }}</p>
                        </x-ui.alert>
                    @else
                        <x-ui.table>
                            <thead>
                                <tr><th>Date</th><th>Location</th><th>Offense</th><th>Vehicle Type</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($violations as $violation)
                                    @if ($violation->violation == 'yes' && $violation->violation_date)
                                        <tr>
                                            <td data-label="Date">{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('m/d/Y') : 'N/A' }}</td>
                                            <td data-label="Location">{{ $violation->violation_location ?? 'N/A' }}</td>
                                            <td data-label="Offense">{{ $violation->offense ?? 'N/A' }}</td>
                                            <td data-label="Vehicle type">{{ $violation->vehicle_type ?? 'N/A' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </x-ui.table>
                        <dl class="mt-4 grid grid-cols-2 gap-3 rounded-lg border bg-muted/40 p-4">
                            <x-ui.detail label="Signature" :value="$violations->first()->violation_record_signature ?? 'N/A'" />
                            <x-ui.detail label="Date Signed" :value="$signedDate($violations->first())" />
                        </dl>
                    @endif
                </x-ui.card>
            @endif

            <x-application.step-actions :back="route('public.application.step4', ['driver_id' => $driver->id, 'slug' => $company->slug])" back-label="Back to Step 4" submit="Save & Continue to Step 6" />
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial state based on violation selection
            const noViolationsRadio = document.querySelector('input[name="violation"][value="no"]');
            const hasViolationsRadio = document.querySelector('input[name="violation"][value="yes"]');
            const violationTable = document.getElementById('violation_fields');

            function toggleViolationTable() {
                if (noViolationsRadio && noViolationsRadio.checked) {
                    // If "no violations" is checked, hide the violation table
                    violationTable.style.display = 'none';
                } else if (hasViolationsRadio && hasViolationsRadio.checked) {
                    // If "has violations" is checked, show the violation table
                    violationTable.style.display = 'block';
                }
            }

            // Add event listeners to radio buttons
            if (noViolationsRadio) {
                noViolationsRadio.addEventListener('change', toggleViolationTable);
            }
            if (hasViolationsRadio) {
                hasViolationsRadio.addEventListener('change', toggleViolationTable);
            }

            // Set initial state
            toggleViolationTable();
        });

        // Form validation
        const form = document.getElementById('step-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const signature = document.getElementById('applicant_signature');
                const dateSigned = document.getElementById('date_signed');
                const noViolationsRadio = document.querySelector('input[name="violation"][value="no"]');
                const hasViolationsRadio = document.querySelector('input[name="violation"][value="yes"]');

                // Validate radio selection
                if (!noViolationsRadio.checked && !hasViolationsRadio.checked) {
                    e.preventDefault();
                    showAppAlert('Please select whether you have had violations or not.');
                    return false;
                }

                // If "has violations" is selected, check if at least one date is filled
                if (hasViolationsRadio && hasViolationsRadio.checked) {
                    const violationDates = document.querySelectorAll('input[name="violation_date[]"]');
                    let hasValidViolation = false;

                    violationDates.forEach(input => {
                        if (input.value.trim() !== '') {
                            hasValidViolation = true;
                        }
                    });

                    if (!hasValidViolation) {
                        e.preventDefault();
                        showAppAlert(
                            'Please add at least one violation with a date, or select "I have had no violations".'
                            );
                        return false;
                    }
                }

                // Validate signature
                if (!signature.value.trim()) {
                    e.preventDefault();
                    showAppAlert('Please provide your signature.');
                    signature.focus();
                    return false;
                }

                // Validate date
                if (!dateSigned.value) {
                    e.preventDefault();
                    showAppAlert('Please select the date signed.');
                    dateSigned.focus();
                    return false;
                }
            });
        }

        // violation fields management
        let violationCount =
            {{ $driver->violations && $driver->violations->isNotEmpty() && $driver->violations->first()->violation == 'yes' ? $driver->violations->count() : 1 }};
        const violationFields = document.getElementById('violation_fields');
        const violationAddBtn = document.getElementById('violation_add');
        const violationRemoveBtn = document.getElementById('violation_remove');
        const violationTableBody = document.querySelector('#violation_fields tbody');

        if (violationAddBtn && violationTableBody) {
            violationAddBtn.addEventListener('click', function() {
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                        <td data-label="Date"><input type="date" name="violation_date[]" class="input w-full" aria-label="Violation date"></td>
                        <td data-label="Location"><input type="text" name="violation_location[]" class="input w-full" aria-label="Violation location"></td>
                        <td data-label="Offense"><input type="text" name="offense[]" placeholder="Enter offense" class="input w-full" aria-label="Offense"></td>
                        <td data-label="Vehicle type"><input type="text" name="vehicle_type[]" placeholder="Enter vehicle type" class="input w-full" aria-label="Vehicle type"></td>
                    `;
                    violationTableBody.appendChild(newRow);
                violationCount++;

                // Show the table if it was hidden
                violationFields.style.display = 'block';
            });
        }

        if (violationRemoveBtn && violationTableBody) {
            violationRemoveBtn.addEventListener('click', function() {
                const rows = violationTableBody.querySelectorAll('tr');
                if (rows.length > 1) { // Keep at least one row
                    rows[rows.length - 1].remove();
                    violationCount--;

                    // If no rows left (shouldn't happen due to min 1), hide the table
                    if (violationCount <= 1) {
                        const remainingRows = violationTableBody.querySelectorAll('tr');
                        let hasData = false;
                        remainingRows.forEach(row => {
                            const inputs = row.querySelectorAll('input');
                            inputs.forEach(input => {
                                if (input.value.trim() !== '') {
                                    hasData = true;
                                }
                            });
                        });

                        if (!hasData) {
                            violationFields.style.display = 'none';
                            const noViolationsRadio = document.querySelector('input[name="violation"][value="no"]');
                            if (noViolationsRadio) {
                                noViolationsRadio.checked = true;
                            }
                        }
                    }
                }
            });
        }
    </script>
@endpush
