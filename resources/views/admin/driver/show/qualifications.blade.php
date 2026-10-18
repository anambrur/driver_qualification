@php
    $overall = match ($compliance['status'] ?? '') {
        'danger' => ['danger', 'Critical'],
        'warning' => ['warning', 'Warning'],
        default => ['success', 'Compliant'],
    };
    $docStatus = [
        'missing' => ['danger', 'Missing', 'bg-destructive'],
        'expired' => ['danger', 'Expired', 'bg-destructive'],
        'expiring' => ['warning', 'Expiring soon', 'bg-warning'],
        'valid' => ['success', 'Valid', 'bg-success'],
    ];
    $percentage = (int) ($compliance['percentage'] ?? 0);
@endphp
<x-ui.card title="Qualifications checklist">
    <x-slot:action>
        <x-ui.badge :tone="$overall[0]" dot>{{ $overall[1] }}</x-ui.badge>
    </x-slot:action>

    <div class="mb-5">
        <div class="mb-1.5 flex items-baseline justify-between text-sm">
            <span class="text-muted-foreground">{{ $compliance['compliant_docs'] ?? 0 }} of {{ $compliance['total_docs'] ?? 0 }} documents complete</span>
            <span class="font-semibold tabular-nums">{{ $percentage }}%</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100" aria-label="Qualification progress">
            <div class="h-full rounded-full bg-primary" style="width: {{ $percentage }}%"></div>
        </div>
    </div>

    @forelse ($compliance['document_details'] ?? [] as $docDetail)
        @php
            [$tone, $label, $bar] = $docStatus[$docDetail['status']] ?? $docStatus['valid'];
        @endphp
        @if ($loop->first)
            <ul class="divide-y divide-border rounded-lg border border-border" role="list">
        @endif
        <li class="relative flex flex-col gap-3 py-3 pl-5 pr-3 sm:flex-row sm:items-center">
            <span class="absolute inset-y-2 left-1.5 w-1 rounded-full {{ $bar }}" aria-hidden="true"></span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium">{{ $docDetail['type_name'] }}</p>
                    <x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
                </div>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    @if ($docDetail['file_date'])
                        Filed {{ \Carbon\Carbon::parse($docDetail['file_date'])->format('M d, Y') }}
                    @endif
                    @if ($docDetail['expiry_date'])
                        @if ($docDetail['file_date']) · @endif
                        Expires {{ \Carbon\Carbon::parse($docDetail['expiry_date'])->format('M d, Y') }}
                        @if (! is_null($docDetail['days_until_expiry']) && $docDetail['status'] === 'expiring')
                            ({{ (int) $docDetail['days_until_expiry'] }} days)
                        @endif
                    @endif
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                @if ($docDetail['document_id'] && $docDetail['file_id'])
                    <x-ui.button :href="route('admin.compliance.driver.documents.view', $docDetail['document_id'])" target="_blank" rel="noopener" variant="ghost" size="icon-sm" icon="eye" aria-label="View {{ $docDetail['type_name'] }}" title="View" />
                    <x-ui.button :href="route('admin.compliance.driver.documents.download', $docDetail['document_id'])" variant="ghost" size="icon-sm" icon="download" aria-label="Download {{ $docDetail['type_name'] }}" title="Download" />
                @endif
                @can('drivers.edit')
                    <x-ui.button :variant="$docDetail['status'] === 'valid' ? 'outline' : null" size="sm"
                        onclick="openUploadModal({{ $driver->id }}, {{ $docDetail['type_id'] }}, 'driver')">
                        {{ $docDetail['status'] === 'valid' ? 'Replace' : 'Complete' }}
                    </x-ui.button>
                    <x-ui.button variant="ghost" size="icon-sm" icon="bell" aria-label="Send reminder for {{ $docDetail['type_name'] }}" title="Send reminder"
                        onclick="sendReminderEmail({{ $driver->id }}, {{ $docDetail['type_id'] }}, 'driver')" />
                @endcan
            </div>
        </li>
        @if ($loop->last)
            </ul>
        @endif
    @empty
        <x-ui.empty-state icon="clipboard-list" title="No document types" description="No active driver document types are configured." class="border-0 p-6!" />
    @endforelse
</x-ui.card>
