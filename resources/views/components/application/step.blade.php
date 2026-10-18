{{--
    Shell for the 10 application steps: company bar, heading, error summary, form, progress sidebar.
    Optional slots: `meta` (heading line), `details` (Driver Information rows), `after` (end of the sidebar).
    `admin` renders it inside the main layout for the admin wizard: no company bar, step line or withdraw card,
    and the progress bar links each step in `edit` mode.
--}}
@props(['company' => null, 'driver', 'step', 'title', 'description' => null, 'admin' => false, 'edit' => false])
<div class="{{ $admin ? 'p-4 sm:p-6 lg:p-8' : 'min-h-dvh bg-muted/40 px-4 py-6 sm:py-8' }}">
    <div class="mx-auto w-full {{ $admin ? 'max-w-7xl' : 'max-w-6xl' }}">
        @unless ($admin)
        <header class="mb-6 flex items-center justify-between gap-4">
            <p class="min-w-0 truncate text-lg font-semibold tracking-tight">{{ $company->company_name }}</p>
            <x-ui.theme-toggle />
        </header>
        @endunless

        <div class="mb-6">
            @unless ($admin)
            <p class="text-sm font-medium text-primary">Step {{ $step }} of 10</p>
            @endunless
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $title }}</h1>
            @if ($description)
                <p class="mt-1 text-muted-foreground">{{ $description }}</p>
            @endif
            <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                <span class="inline-flex items-center gap-1.5"><x-ui.icon name="user" class="size-4" /> Driver: {{ $driver->first_name }} {{ $driver->last_name }}</span>
                @if ($driver->company)
                    <span class="inline-flex items-center gap-1.5"><x-ui.icon name="building-2" class="size-4" /> Company: {{ $driver->company->company_name }}</span>
                @endif
                {{ $meta ?? '' }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="grid min-w-0 content-start gap-6">
                @if ($errors->any())
                    <x-ui.alert tone="danger" title="Please fix the following errors:">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                {{ $slot }}
            </div>

            <aside class="grid content-start gap-6" aria-label="Application progress">
                {{ $aside ?? '' }}

                @if ($admin)
                @include('components.progress-bar', ['currentStep' => $step, 'totalSteps' => 10, 'isEditMode' => $edit, 'driver_id' => $driver->id])
                @else
                @include('components.progress-bar', ['currentStep' => $step, 'totalSteps' => 10, 'isEditMode' => false, 'finishLaterUrl' => route('application.form', $company->slug)])

                @include('application.partials.withdraw-application', ['company' => $company, 'driver' => $driver])
                @endif

                <x-ui.card title="Driver Information" size="sm">
                    <dl class="grid gap-3">
                        <x-ui.detail label="Name" :value="trim($driver->first_name.' '.$driver->last_name)" />
                        <x-ui.detail label="Email" :value="$driver->email" />
                        <x-ui.detail label="Phone" :value="$driver->main_phone" />
                        @if ($driver->company)
                            <x-ui.detail label="Company" :value="$driver->company->company_name" />
                        @endif
                        <x-ui.detail label="Status">
                            <x-ui.badge :tone="$driver->status == 'active' ? 'success' : 'warning'">{{ ucfirst($driver->status) }}</x-ui.badge>
                        </x-ui.detail>
                        {{ $details ?? '' }}
                    </dl>
                </x-ui.card>

                {{ $after ?? '' }}
            </aside>
        </div>
    </div>
</div>
