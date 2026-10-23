<x-ui.page-header :title="$driverFullName ?: 'Driver'">
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[['label' => 'Drivers', 'href' => route('admin.driver.index')], ['label' => $driverFullName ?: 'Driver']]" />
    </x-slot:breadcrumbs>
    <x-slot:actions>
        <x-ui.button :href="route('admin.driver.index')" variant="outline" icon="arrow-left">Back</x-ui.button>
        @feature('zip_export')
            @if (app(\App\Services\Exports\ExportService::class)->canRequest(auth()->user(), 'zip_driver'))
                <form action="{{ route('exports.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="type" value="zip_driver">
                    <input type="hidden" name="driver_id" value="{{ $driver->id }}">
                    <x-ui.button type="submit" variant="outline" icon="archive">Download ZIP</x-ui.button>
                </form>
            @endif
        @endfeature
        @if ($driver->status !== 'draft')
            <x-ui.button :href="route('admin.driver.edit', $driver->id)" icon="pencil">Edit driver</x-ui.button>
        @endif
    </x-slot:actions>
</x-ui.page-header>

<x-ui.card>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
        @include('partials.ui.avatar', ['name' => $driverFullName, 'src' => $driverPhotoUrl, 'size' => null, 'class' => 'size-16! text-lg'])
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-lg font-semibold text-foreground">{{ $driverFullName }}</p>
                @include('partials.ui.status-badge', ['status' => $driver->status])
            </div>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground">
                <span class="inline-flex items-center gap-1.5">{{ svg('lucide-phone', 'size-4', ['aria-hidden' => 'true']) }}<span class="sr-only">Phone:</span> {{ $driver->main_phone ?: 'No phone' }}</span>
                <span class="inline-flex min-w-0 items-center gap-1.5">{{ svg('lucide-mail', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}<span class="sr-only">Email:</span> <span class="truncate">{{ $driver->email ?: 'No email' }}</span></span>
                @if ($driver->hired_at)
                    <span class="inline-flex items-center gap-1.5">{{ svg('lucide-calendar-check', 'size-4', ['aria-hidden' => 'true']) }} Hired {{ \Carbon\Carbon::parse($driver->hired_at)->format('M d, Y') }}</span>
                @endif
            </div>
        </div>
        <div class="sm:text-right">
            <p class="text-xs text-muted-foreground">Business</p>
            <p class="text-sm font-medium">{{ $driver->company->company_name ?? ($driver->business_name ?: '—') }}</p>
        </div>
    </div>
</x-ui.card>
