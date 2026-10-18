{{-- Admin driver create/edit. Needs $form (DriverForm), $action, $title, $description. --}}
@php
    $driver = $form->driver;
    $v = $form->values;
    $breadcrumbs = $driver
        ? [['label' => 'Drivers', 'href' => route('admin.driver.index')], ['label' => trim($driver->first_name.' '.$driver->last_name) ?: 'Driver', 'href' => route('admin.driver.show', $driver->id)], ['label' => 'Edit']]
        : [['label' => 'Drivers', 'href' => route('admin.driver.index')], ['label' => 'New driver']];
    $sections = [
        'section-company' => 'Company',
        'section-applicant' => 'Applicant',
        'section-residences' => 'Residences',
        'section-license' => 'License',
        'section-experience' => 'Experience',
        'section-accidents' => 'Accidents',
        'section-violations' => 'Violations',
        'section-forfeitures' => 'Forfeitures',
        'section-employment' => 'Employment',
    ];
@endphp

<div class="p-4 sm:p-6 lg:p-8">
    <x-ui.page-header :title="$title" :description="$description">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="$breadcrumbs" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @php
        $fieldErrors = collect($errors->messages())->except('system_error');
    @endphp
    @if ($errors->has('system_error'))
        <x-ui.alert tone="danger" title="The driver could not be saved" class="mb-6">{{ $errors->first('system_error') }}</x-ui.alert>
    @endif
    @if ($fieldErrors->isNotEmpty())
        <x-ui.alert tone="danger" title="Please fix {{ $fieldErrors->count() === 1 ? 'this field' : 'these '.$fieldErrors->count().' fields' }}" class="mb-6">
            <ul class="list-inside list-disc">
                @foreach ($fieldErrors as $key => $messages)
                    <li><a href="#{{ \App\Support\Ui\Field::id($key) }}" class="underline underline-offset-2">{{ $messages[0] }}</a></li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <form action="{{ $action }}" method="POST" id="driverForm" enctype="multipart/form-data" class="min-w-0 space-y-6">
            @csrf
            @if ($driver)
                @method('PUT')
            @endif

            <nav aria-label="Form sections" class="-mx-4 overflow-x-auto px-4 lg:hidden">
                <ul class="flex w-max gap-2" role="list">
                    @foreach ($sections as $id => $label)
                        <li><a href="#{{ $id }}" data-section-link class="badge" data-variant="outline">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            @include('admin.driver.form.company')
            @include('admin.driver.form.applicant')
            @include('admin.driver.form.residences')
            @include('admin.driver.form.license')
            @include('admin.driver.form.experience')
            @include('admin.driver.form.history', ['group' => 'accidents', 'answer' => 'accident', 'id' => 'section-accidents',
                'title' => 'Accidents and crashes, previous 3 years', 'question' => 'Has the driver had any accidents or crashes in the last 3 years?',
                'addLabel' => 'Add accident'])
            @include('admin.driver.form.history', ['group' => 'violations', 'answer' => 'violation', 'id' => 'section-violations',
                'title' => 'Traffic violations, previous 3 years', 'question' => 'Has the driver had any moving traffic violations in the last 3 years?',
                'addLabel' => 'Add violation'])
            @include('admin.driver.form.forfeitures')
            @include('admin.driver.form.employment')

            <div class="sticky bottom-0 z-10 -mx-4 border-t border-border bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:rounded-xl sm:border sm:px-6 sm:py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="hidden text-sm text-muted-foreground sm:block">
                        {{ $driver ? 'Saves the changes and continues to the license documents.' : 'Saves as a draft and continues to the license documents.' }}
                    </p>
                    <div class="flex gap-2 *:flex-1 sm:*:flex-none">
                        <x-ui.button variant="outline" :href="$driver ? route('admin.driver.show', $driver->id) : route('admin.driver.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon-end="arrow-right">{{ $driver ? 'Save and continue' : 'Continue' }}</x-ui.button>
                    </div>
                </div>
            </div>
        </form>

        <aside class="space-y-6 lg:sticky lg:top-[calc(var(--header-height)+1.5rem)] lg:max-h-[calc(100dvh-var(--header-height)-3rem)] lg:self-start lg:overflow-y-auto custom-scrollbar">
            <nav aria-label="Form sections" class="hidden lg:block">
                <p class="mb-2 px-3 text-xs font-medium text-muted-foreground">On this page</p>
                <ul class="grid gap-0.5 border-l border-border" role="list">
                    @foreach ($sections as $id => $label)
                        <li>
                            <a href="#{{ $id }}" data-section-link
                                class="-ml-px block border-l-2 border-transparent px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground aria-[current=true]:border-primary aria-[current=true]:font-medium aria-[current=true]:text-foreground">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
            @include('components.progress-bar', [
                'currentStep' => 1,
                'isEditMode' => $isEditMode ?? false,
                'driver_id' => $driver?->id,
            ])
        </aside>
    </div>
</div>

@push('scripts')
    @vite('resources/js/driver-form.js')
    <script src="{{ asset('js/driver-form-validation.js') }}"></script>
@endpush
