@php
    // Default values
    $currentStep = $currentStep ?? 1;
    $totalSteps = $totalSteps ?? 10;
    $isEditMode = $isEditMode ?? false;
    $driverId = $driver_id ?? ($driverId ?? null);

    // Function to get edit step URL (guarded: the partial can render more than once per process)
    if (! function_exists('getEditStepUrl')) {
    function getEditStepUrl($step, $driverId)
    {
        $routes = [
            1 => route('admin.driver.edit', ['id' => $driverId]),
            2 => route('admin.driver.license', ['driver_id' => $driverId]) . '?edit=1',
            3 => route('admin.driver.medical.card', ['driver_id' => $driverId]) . '?edit=1',
            4 => route('admin.driver.forfeiture', ['driver_id' => $driverId]) . '?edit=1',
            5 => route('admin.driver.violation', ['driver_id' => $driverId]) . '?edit=1',
            6 => route('admin.driver.alcohol.and.drug.test', ['driver_id' => $driverId]) . '?edit=1',
            7 => route('admin.driver.fmcsa.consent', ['driver_id' => $driverId]) . '?edit=1',
            8 => route('admin.driver.psp', ['driver_id' => $driverId]) . '?edit=1',
            9 => route('admin.driver.alcohol.and.drug.test.policy', ['driver_id' => $driverId]) . '?edit=1',
            10 => route('admin.driver.general.work.policy', ['driver_id' => $driverId]) . '?edit=1',
        ];

        return $routes[$step] ?? '#';
    }
    }
@endphp

<x-ui.card size="sm" :title="$isEditMode ? 'Document Edit Process' : 'Application Process'">
    @if ($isEditMode)
        <x-ui.alert tone="info" icon="pencil" title="Edit Mode" class="mb-4">
            You are editing documents for this driver. Changes will be saved immediately.
        </x-ui.alert>
    @endif

    <ol class="grid">
        @foreach ([
        1 => ['title' => 'Application For Employment', 'description' => 'Your general application information.'],
        2 => ['title' => 'Upload Driver License Copy', 'description' => 'Provide a photo copy of your driver license.'],
        3 => ['title' => 'Upload Medical Card Copy', 'description' => 'Provide a photo copy of your medical card.'],
        4 => ['title' => 'Upload Forfeiture Documents', 'description' => 'Provide forfeiture documents (if applicable).'],
        5 => ['title' => 'Violation Record & Annual Review', 'description' => 'Your violations & annual review agreement.'],
        6 => ['title' => 'Alcohol & Drug Test Statement', 'description' => 'Your alcohol & drug testing history statement.'],
        7 => ['title' => 'FMCSA Clearinghouse Consent', 'description' => 'Authorization for FMCSA Clearinghouse queries.'],
        8 => ['title' => 'PSP Driver Authorization', 'description' => 'Authorization to run DOT PSP report.'],
        9 => ['title' => 'Alcohol & Drug Testing Policy', 'description' => 'Review employer\'s alcohol & drug testing policy.'],
        10 => ['title' => 'General Work Policy', 'description' => 'Review employer\'s general work policy.'],
    ] as $step => $stepData)
            <li class="flex gap-3" @if ($step == $currentStep) aria-current="step" @endif>
                <div class="flex flex-col items-center">
                    <span @class([
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-primary text-primary-foreground' => $step < $currentStep,
                        'border-2 border-primary text-primary' => $step == $currentStep,
                        'border-2 border-border text-muted-foreground' => $step > $currentStep,
                    ])>
                        @if ($step < $currentStep)
                            <x-ui.icon name="check" class="size-3.5" />
                            <span class="sr-only">Completed:</span>
                        @else
                            {{ $step }}
                        @endif
                    </span>
                    @if ($step < $totalSteps)
                        <span @class(['my-1 w-0.5 flex-1 min-h-6', 'bg-primary' => $step < $currentStep, 'bg-border' => $step >= $currentStep])></span>
                    @endif
                </div>
                <div class="min-w-0 flex-1 pb-4">
                    @if ($isEditMode && $driverId)
                        <a href="{{ getEditStepUrl($step, $driverId) }}" class="-m-1.5 block rounded-md p-1.5 hover:bg-accent">
                            <p @class(['text-sm font-medium', 'text-foreground' => $step <= $currentStep, 'text-muted-foreground' => $step > $currentStep])>{{ $stepData['title'] }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ $stepData['description'] }}</p>
                        </a>
                    @else
                        <p @class(['text-sm font-medium', 'text-foreground' => $step <= $currentStep, 'text-muted-foreground' => $step > $currentStep])>{{ $stepData['title'] }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ $stepData['description'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <x-slot:footer class="grid border-t pt-4">
        @if ($isEditMode && $driverId)
            <x-ui.button :href="route('admin.driver.edit', ['id' => $driverId])" variant="outline" icon="arrow-left" class="w-full">Back to Driver Edit</x-ui.button>
        @else
            <x-ui.button variant="outline" class="w-full">Save & Finish Later</x-ui.button>
            <p class="mt-2 text-center text-xs text-muted-foreground">Your application is not sent or visible to the employer until it is 100% complete.</p>
        @endif
    </x-slot:footer>
</x-ui.card>
