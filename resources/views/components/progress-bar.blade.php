@php
    // Default values
    $currentStep = $currentStep ?? 1;
    $totalSteps = $totalSteps ?? 10;
@endphp

<div class="bg-white border border-gray-200 rounded-lg shadow-theme-xs dark:bg-gray-800 dark:border-gray-700 p-4">
    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Application Process</h3>

    <!-- Progress Steps -->
    <div class="space-y-6">
        @foreach ([
        1 => ['title' => 'Application For Employment', 'description' => 'Your general application information.'],
        2 => ['title' => 'Upload Driver License Copy', 'description' => 'Provide a photo copy of your driver license.'],
        3 => ['title' => 'Upload Medical Card Copy', 'description' => 'Provide a photo copy of your medical card.'],
        4 => ['title' => 'Upload Forfeiture Documents', 'description' => 'Provide forfeiture documents (if applicable).'],
        5 => ['title' => 'Violation Record & Annual Review', 'description' => 'Your violations & annual review agreement.'],
        6 => ['title' => 'Alcohol & Drug Test Statement', 'description' => 'Your alcohol & drug testing history statement.'],
        7 => ['title' => 'Safety Performance History Investigation', 'description' => 'Previous employer investigation agreement.'],
        8 => ['title' => 'PSP Driver Authorization', 'description' => 'Authorization to run DOT PSP report.'],
        9 => ['title' => 'Alcohol & Drug Testing Policy', 'description' => 'Review employer\'s alcohol & drug testing policy.'],
        10 => ['title' => 'General Work Policy', 'description' => 'Review employer\'s general work policy.'],
    ] as $step => $stepData)
            <div class="flex items-start space-x-3">
                <div class="flex flex-col items-center">
                    @if ($step < $currentStep)
                        <!-- Completed Step -->
                        <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    @elseif($step == $currentStep)
                        <!-- Current Step -->
                        <div
                            class="w-8 h-8 rounded-full border-2 border-brand-500 bg-white flex items-center justify-center">
                            <span class="text-brand-500 text-sm font-medium">{{ $step }}</span>
                        </div>
                    @else
                        <!-- Upcoming Step -->
                        <div
                            class="w-8 h-8 rounded-full border-2 border-gray-300 dark:border-gray-600 bg-white flex items-center justify-center">
                            <span
                                class="text-gray-400 dark:text-gray-500 text-sm font-medium">{{ $step }}</span>
                        </div>
                    @endif

                    <!-- Connecting Line (except for last step) -->
                    @if ($step < $totalSteps)
                        <div
                            class="h-16 w-0.5 mt-1 
                            @if ($step < $currentStep) bg-brand-500 
                            @else bg-gray-200 dark:bg-gray-700 @endif">
                        </div>
                    @endif
                </div>
                <div class="pt-1 flex-1">
                    <p
                        class="@if ($step <= $currentStep) font-medium text-gray-800 dark:text-white/90 @else font-medium text-gray-500 dark:text-gray-400 @endif">
                        {{ $stepData['title'] }}
                    </p>
                    <p
                        class="text-sm @if ($step <= $currentStep) text-gray-500 dark:text-gray-400 @else text-gray-400 dark:text-gray-500 @endif mt-1">
                        {{ $stepData['description'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Save & Finish Later Button -->
    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
        <button type="button"
            class="w-full inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
            Save & Finish Later
        </button>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 text-center">Your application is not sent or visible to
            the employer until it is 100% complete.</p>
    </div>
</div>
