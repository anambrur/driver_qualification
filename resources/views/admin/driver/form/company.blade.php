<x-ui.card id="section-company" title="Company" class="scroll-mt-24">
    <p class="text-sm text-muted-foreground">The driver is added to</p>
    <p class="mt-1 font-medium">{{ $driver?->company?->company_name ?? auth()->user()->company?->company_name ?? 'Your company' }}</p>
</x-ui.card>
