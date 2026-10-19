@extends('layouts.main-layout')

@section('title', 'Driver Compliance')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Driver Compliance Dashboard" :description="'Monitor document compliance across '.$totalDrivers.' drivers'">
            <x-slot:actions>
                <x-ui.button :href="route('admin.driver.create')" icon="user-plus">Add Driver</x-ui.button>
                <x-ui.button id="refresh-dashboard" variant="outline" size="icon" icon="refresh-cw" class="max-sm:flex-none!" title="Refresh" aria-label="Refresh" />
            </x-slot:actions>
        </x-ui.page-header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat label="Total Drivers" :value="$totalDrivers" icon="users" tone="brand" />
            <x-ui.stat label="Compliant" :value="$totalCompliant" icon="circle-check" tone="success" />
            <x-ui.stat label="Warning" :value="$totalWarning" icon="triangle-alert" tone="warning" />
            <x-ui.stat label="Critical" :value="$totalCritical" icon="circle-x" tone="danger" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-ui.card title="Driver Compliance Status" description="Grouped by driver - all document issues" class="lg:col-span-2">
                <x-slot:action>
                    <x-ui.badge tone="warning">{{ $totalWarning + $totalCritical }} Drivers Need Attention</x-ui.badge>
                </x-slot:action>

                <div class="space-y-4">
                    @forelse ($drivers as $driver)
                        <x-compliance.row kind="driver" :item="$driver" :title="$driver['full_name']" icon="user">
                            <span aria-hidden="true">•</span>
                            <span class="inline-flex items-center gap-1">
                                {{ svg('lucide-id-card', 'size-3.5', ['aria-hidden' => 'true']) }}
                                {{ $driver['license_state'] ?? 'N/A' }}
                            </span>
                            @if ($driver['hire_date'])
                                <span aria-hidden="true">•</span>
                                <span>Hired: {{ \Carbon\Carbon::parse($driver['hire_date'])->format('M d, Y') }}</span>
                            @endif
                        </x-compliance.row>
                    @empty
                        <x-ui.empty-state icon="users" title="No Drivers Found" description="Add drivers to start tracking compliance">
                            <x-ui.button :href="route('admin.driver.create')" size="sm" icon="plus">Add First Driver</x-ui.button>
                        </x-ui.empty-state>
                    @endforelse
                </div>

                @if ($drivers->hasPages())
                    <div class="mt-6">
                        {{ $drivers->links() }}
                    </div>
                @endif
            </x-ui.card>

            <div class="space-y-6">
                <x-ui.card title="Quick Actions">
                    <div class="space-y-3">
                        @foreach ([
                            [route('admin.driver.create'), 'user-plus', 'brand', 'Add New Driver', 'Register a new driver'],
                            [route('admin.driver.index'), 'list', 'info', 'Manage Drivers', 'View and edit driver information'],
                            [route('admin.settings.document-types.index').'?module=driver', 'file-text', 'neutral', 'Document Types', 'Configure driver document requirements'],
                        ] as [$href, $icon, $tone, $label, $hint])
                            @include('admin.compliance.partials.quick-action')
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card title="Compliance Summary">
                    @include('admin.compliance.partials.overall-progress')

                    <dl class="mt-4 space-y-2 text-sm">
                        @foreach ([['success', 'Compliant', $totalCompliant], ['warning', 'Warning (Expiring Soon)', $totalWarning], ['danger', 'Critical (Missing/Expired)', $totalCritical]] as [$tone, $label, $count])
                            <div class="flex items-center justify-between gap-2">
                                <dt class="flex items-center gap-2 text-muted-foreground">
                                    <span data-tone="{{ $tone }}" class="size-2 rounded-full bg-[var(--tone)]" aria-hidden="true"></span>{{ $label }}
                                </dt>
                                <dd class="font-medium tabular-nums text-foreground">{{ $count }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>
            </div>
        </div>
    </div>

    <x-ui.dialog id="driverDetailsModal" title="Driver details" size="lg">
        <div id="driverDetailsContent"></div>
        <x-slot:footer>
            <x-ui.button variant="outline" data-action="close-dialog">Close</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    @include('admin.compliance.partials.preview-dialog')

    @include('admin.compliance.partials.driver-upload-document-modal')
@endsection

@push('scripts')
    <script>
        @include('admin.compliance.partials.page-script')

        const dateLabel = value => new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        function showDetails(kind, driverId) {
            showDriverDetails(driverId);
        }

        function showDriverDetails(driverId) {
            const content = document.getElementById('driverDetailsContent');
            document.getElementById('driverDetailsModal-title').textContent = 'Driver details';
            content.innerHTML = loadingHtml('Loading driver details...');
            document.getElementById('driverDetailsModal').showModal();

            fetch(`/admin/compliance/drivers/${driverId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const driver = data.driver;
                        document.getElementById('driverDetailsModal-title').textContent =
                            `${driver.full_name} - Compliance Details`;

                        let documentsHtml = '';
                        if (driver.documents && driver.documents.length > 0) {
                            documentsHtml = driver.documents.map(doc => {
                                const statusTone = doc.days_until_expiry < 0 ? 'danger' :
                                    doc.days_until_expiry <= 30 && doc.days_until_expiry > 0 ? 'warning' : 'success';

                                const statusText = doc.days_until_expiry < 0 ? 'Expired' :
                                    doc.days_until_expiry <= 30 && doc.days_until_expiry > 0 ?
                                    `Expires in ${doc.days_until_expiry} days` :
                                    'Valid';

                                const fileUrl = `/admin/compliance/driver-documents/${doc.id}/view`;
                                const fileExtension = doc.file_path ? doc.file_path.split('.').pop()
                                    .toLowerCase() : '';
                                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);

                                return `
                                    <div class="flex items-start justify-between gap-3 rounded-lg border p-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                                <h4 class="font-medium text-foreground">${escapeHtml(doc.type_name)}</h4>
                                                <span class="badge" data-tone="${statusTone}">${statusText}</span>
                                            </div>
                                            <dl class="grid grid-cols-2 gap-2 text-sm">
                                                ${doc.file_date ? `
                                                    <div>
                                                        <dt class="text-muted-foreground">Filed Date:</dt>
                                                        <dd class="font-medium text-foreground">${dateLabel(doc.file_date)}</dd>
                                                    </div>
                                                ` : ''}
                                                ${doc.expiry_date ? `
                                                    <div>
                                                        <dt class="text-muted-foreground">Expiry Date:</dt>
                                                        <dd class="font-medium text-foreground">${dateLabel(doc.expiry_date)}</dd>
                                                    </div>
                                                ` : ''}
                                                ${doc.description ? `
                                                    <div class="col-span-2">
                                                        <dt class="text-muted-foreground">Description:</dt>
                                                        <dd class="font-medium text-foreground">${escapeHtml(doc.description)}</dd>
                                                    </div>
                                                ` : ''}
                                            </dl>
                                        </div>
                                        ${doc.file_path ? `
                                            <div class="flex shrink-0 gap-1">
                                                ${isImage ? `
                                                    <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" data-action="preview-document" data-url="${escapeHtml(fileUrl)}" data-title="${escapeHtml(doc.type_name)}" title="Preview" aria-label="Preview">
                                                        ${icon('eye')}
                                                    </button>
                                                ` : ''}
                                                <a href="/admin/compliance/driver-documents/${Number(doc.id)}/download" class="btn" data-variant="ghost" data-size="icon-sm" title="Download" aria-label="Download">
                                                    ${icon('download')}
                                                </a>
                                                <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" data-action="view-document" data-url="${escapeHtml(fileUrl)}" title="View" aria-label="View">
                                                    ${icon('file-text')}
                                                </button>
                                            </div>
                                        ` : ''}
                                    </div>
                                `;
                            }).join('');
                        } else {
                            documentsHtml = '<p class="py-4 text-center text-muted-foreground">No documents uploaded yet</p>';
                        }

                        const missingDocsHtml = driver.missing_documents && driver.missing_documents.length > 0 ? `
                            <section>
                                <h4 class="mb-2 font-medium text-foreground">Missing/Expired Documents:</h4>
                                <ul class="space-y-2">
                                    ${driver.missing_documents.map(doc => `
                                        <li class="flex items-center gap-3 rounded-lg border p-2 text-sm text-foreground">
                                            ${icon('circle-alert', 'size-4 shrink-0 text-destructive')}
                                            <span>${escapeHtml(doc)}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </section>
                        ` : '';

                        const expiringDocsHtml = driver.expiring_documents && driver.expiring_documents.length > 0 ? `
                            <section>
                                <h4 class="mb-2 font-medium text-foreground">Expiring Soon:</h4>
                                <ul class="space-y-2">
                                    ${driver.expiring_documents.map(doc => `
                                        <li class="flex items-center gap-3 rounded-lg border p-2 text-sm text-foreground">
                                            ${icon('triangle-alert', 'size-4 shrink-0 text-warning')}
                                            <span>${escapeHtml(doc)}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </section>
                        ` : '';

                        const tone = complianceTone(driver.compliance_status);

                        content.innerHTML = `
                            <div class="space-y-5">
                                <dl class="grid grid-cols-1 gap-4 rounded-lg bg-muted/50 p-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-muted-foreground">Email</dt>
                                        <dd class="break-words font-medium text-foreground">${escapeHtml(driver.email || 'N/A')}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Phone</dt>
                                        <dd class="font-medium text-foreground">${escapeHtml(driver.phone || 'N/A')}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">License Number</dt>
                                        <dd class="font-medium text-foreground">${escapeHtml(driver.license_number || 'N/A')}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">License State</dt>
                                        <dd class="font-medium text-foreground">${escapeHtml(driver.license_state || 'N/A')}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Hire Date</dt>
                                        <dd class="font-medium text-foreground">${driver.hire_date ? new Date(driver.hire_date).toLocaleDateString() : 'N/A'}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Status</dt>
                                        <dd class="font-medium capitalize text-foreground">${escapeHtml(driver.status || 'N/A')}</dd>
                                    </div>
                                </dl>

                                <section>
                                    <h4 class="mb-2 font-medium text-foreground">Compliance Status</h4>
                                    <div data-tone="${tone}" class="flex items-center justify-between gap-3 rounded-lg p-3 bg-[color-mix(in_oklch,var(--tone)_8%,var(--card))]">
                                        <div class="text-sm">
                                            <p class="font-medium text-foreground">${escapeHtml(driver.compliance_percentage)} Compliance</p>
                                            <p class="text-muted-foreground">${Number(driver.compliant_docs)} of ${Number(driver.total_docs)} documents</p>
                                        </div>
                                        <span class="badge" data-tone="${tone}">${complianceLabel(driver.compliance_status)}</span>
                                    </div>
                                </section>

                                <section>
                                    <h4 class="mb-3 font-medium text-foreground">Documents</h4>
                                    <div class="space-y-3">
                                        ${documentsHtml}
                                    </div>
                                </section>

                                ${missingDocsHtml}
                                ${expiringDocsHtml}
                            </div>
                        `;
                    } else {
                        content.innerHTML = errorHtml('Failed to load driver details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = errorHtml('Error loading driver details');
                });
        }
    </script>
@endpush
