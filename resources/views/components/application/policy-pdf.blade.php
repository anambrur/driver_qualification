{{-- Company policy PDF (steps 9 and 10): download link and inline viewer, or a "not available" notice. --}}
@props(['title', 'url' => null, 'updatedAt' => null, 'contactPhone' => null, 'contactEmail' => null])
<section class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold">{{ $title }} Document</h3>
        @if ($url)
            <x-ui.button :href="$url" target="_blank" rel="noopener" variant="primary" size="sm" icon="download">Download PDF</x-ui.button>
        @endif
    </div>

    @if ($url)
        <div class="overflow-hidden rounded-lg border">
            <iframe src="{{ $url }}" width="100%" height="600px" class="block border-0" title="{{ $title }} PDF"></iframe>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
            <p>Need help? Call {{ $contactPhone ?? 'N/A' }} or email {{ $contactEmail ?? 'N/A' }}</p>
            <p class="text-xs">Last updated: {{ $updatedAt ? \Carbon\Carbon::parse($updatedAt)->format('m/d/Y') : 'N/A' }}</p>
        </div>
    @else
        <x-ui.empty-state icon="file-x" title="Policy Document Not Available" :description="'The '.$title.' PDF has not been uploaded yet.'">
            <p class="text-xs text-muted-foreground">Please contact your administrator at {{ $contactEmail ?? 'N/A' }}</p>
        </x-ui.empty-state>
    @endif
</section>
