{{--
    One driver, vehicle or trailer on a compliance dashboard: status, document rows and their actions.
    The page script handles data-action (show-details, upload-document, send-reminder).
--}}
@props(['kind', 'item', 'title', 'icon'])
@php
    $tone = ['warning' => 'warning', 'danger' => 'danger'][$item['compliance_status']] ?? 'success';
    $docTone = ['missing' => 'danger', 'expired' => 'danger', 'expiring' => 'warning'];
    $docLabel = ['missing' => 'Missing', 'expired' => 'Expired', 'expiring' => 'Expiring Soon'];
@endphp
<article data-tone="{{ $tone }}" @class([
    'rounded-lg border p-4',
    'bg-[color-mix(in_oklch,var(--tone)_5%,var(--card))]' => $tone !== 'success',
    'bg-muted/25' => $tone === 'success',
])>
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-3">
            <span class="stat-icon flex size-8 shrink-0 items-center justify-center rounded-lg" data-tone="{{ $tone }}">
                {{ svg('lucide-'.$icon, 'size-4', ['aria-hidden' => 'true']) }}
            </span>
            <div class="min-w-0">
                <h3 class="flex flex-wrap items-center gap-2 font-semibold text-foreground">
                    <span class="truncate">{{ $title }}</span>
                    <x-ui.badge :tone="$tone">{{ ['warning' => 'Warning', 'danger' => 'Critical'][$item['compliance_status']] ?? 'Compliant' }}</x-ui.badge>
                </h3>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                    <span class="tabular-nums">{{ $item['compliance_percentage'] }} ({{ $item['compliant_docs'] }}/{{ $item['total_docs'] }})</span>
                    {{ $slot }}
                </p>
            </div>
        </div>
        <x-ui.button variant="outline" size="sm" icon="eye" class="shrink-0" data-action="show-details" :data-kind="$kind" :data-id="$item['id']">View Details</x-ui.button>
    </div>

    <ul class="space-y-2">
        @foreach ($item['document_details'] as $docDetail)
            <li data-tone="{{ $docTone[$docDetail['status']] ?? 'success' }}"
                class="flex flex-wrap items-center gap-3 rounded-lg border border-l-4 border-l-[var(--tone)] bg-card px-3 py-2">
                {{ svg('lucide-file-text', 'size-4 shrink-0 text-muted-foreground', ['aria-hidden' => 'true']) }}
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-foreground">{{ $docDetail['type_name'] }}</div>
                    @if ($docDetail['file_date'] ?? null)
                        <div class="text-xs text-muted-foreground">Filed: {{ \Carbon\Carbon::parse($docDetail['file_date'])->format('M d, Y') }}</div>
                    @endif
                    @if ($docDetail['expiry_date'])
                        <div class="text-xs text-muted-foreground">Expires: {{ \Carbon\Carbon::parse($docDetail['expiry_date'])->format('M d, Y') }}</div>
                    @endif
                </div>
                <x-ui.badge :tone="$docTone[$docDetail['status']] ?? 'success'">{{ $docLabel[$docDetail['status']] ?? 'Valid' }}</x-ui.badge>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($docDetail['status'] !== 'valid')
                        <x-ui.button size="xs" data-action="upload-document" :data-id="$item['id']" :data-type-id="$docDetail['type_id']" :data-asset-type="$kind">Complete</x-ui.button>
                    @endif
                    <x-ui.button variant="ghost" size="icon-sm" icon="mail" title="Send reminder" aria-label="Send reminder"
                        data-action="send-reminder" :data-id="$item['id']" :data-type-id="$docDetail['type_id']" :data-asset-type="$kind" />
                </div>
            </li>
        @endforeach
    </ul>
</article>
