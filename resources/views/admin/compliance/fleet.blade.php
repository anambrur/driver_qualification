@extends('layouts.main-layout')

@section('title', 'Fleet Compliance')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Fleet Compliance Dashboard" :description="'Monitor document compliance across '.($totalVehicles + $totalTrailers).' vehicles & trailers'">
            <x-slot:actions>
                <x-ui.button :href="route('admin.driver.index')" icon="users">Driver Management</x-ui.button>
                <x-ui.button id="refresh-dashboard" variant="outline" size="icon" icon="refresh-cw" class="max-sm:flex-none!" title="Refresh" aria-label="Refresh" />
            </x-slot:actions>
        </x-ui.page-header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <x-ui.stat label="Total Vehicles" :value="$totalVehicles" icon="truck" tone="brand" />
            <x-ui.stat label="Total Trailers" :value="$totalTrailers" icon="container" tone="info" />
            <x-ui.stat label="Compliant" :value="$totalCompliant" icon="circle-check" tone="success" />
            <x-ui.stat label="Warning" :value="$totalWarning" icon="triangle-alert" tone="warning" />
            <x-ui.stat label="Critical" :value="$totalCritical" icon="circle-x" tone="danger" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card title="Vehicle Compliance Status" description="Grouped by vehicle - all document issues">
                    <x-slot:action>
                        <x-ui.badge tone="warning">{{ $vehiclesNeedingAttention }} Vehicles Need Attention</x-ui.badge>
                    </x-slot:action>

                    <div class="space-y-4">
                        @forelse ($vehicles as $vehicle)
                            <x-compliance.row kind="vehicle" :item="$vehicle" :title="'#'.$vehicle['unit_no']" icon="truck">
                                @include('admin.compliance.partials.assigned-driver', ['asset' => $vehicle])
                                @if ($vehicle['odometer'] > 0)
                                    <span aria-hidden="true">•</span>
                                    <span class="tabular-nums">{{ number_format($vehicle['odometer']) }} miles</span>
                                @endif
                            </x-compliance.row>
                        @empty
                            <x-ui.empty-state icon="truck" title="No Vehicles Found" description="Add vehicles to start tracking compliance" />
                        @endforelse
                    </div>

                    @if ($vehicles->hasPages())
                        <div class="mt-6">
                            {{ $vehicles->links() }}
                        </div>
                    @endif
                </x-ui.card>

                <x-ui.card title="Trailer Compliance" description="Trailer document status">
                    <x-slot:action>
                        <x-ui.badge tone="info">{{ $totalTrailers }} Trailers · {{ $trailersNeedingAttention }} Need Attention</x-ui.badge>
                    </x-slot:action>

                    <div class="space-y-4">
                        @forelse ($trailers as $trailer)
                            <x-compliance.row kind="trailer" :item="$trailer" :title="'#'.$trailer['unit_no']" icon="container">
                                @include('admin.compliance.partials.assigned-driver', ['asset' => $trailer])
                            </x-compliance.row>
                        @empty
                            <x-ui.empty-state icon="container" title="No Trailers Found" description="Add trailers to start tracking compliance" />
                        @endforelse
                    </div>

                    @if ($trailers->hasPages())
                        <div class="mt-6">
                            {{ $trailers->links() }}
                        </div>
                    @endif
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-ui.card title="Quick Actions">
                    <div class="space-y-3">
                        @foreach ([
                            [route('admin.vehicle.index'), 'plus', 'brand', 'Add New Vehicle', 'Register a new vehicle'],
                            [route('admin.trailer.index'), 'container', 'info', 'Add New Trailer', 'Register a new trailer'],
                        ] as [$href, $icon, $tone, $label, $hint])
                            @include('admin.compliance.partials.quick-action')
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card title="Compliance Summary">
                    @include('admin.compliance.partials.overall-progress')

                    <div class="mt-4 space-y-3">
                        @foreach ([['success', 'Vehicles', $vehicleCompliance], ['info', 'Trailers', $trailerCompliance]] as [$tone, $label, $percent])
                            <div>
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ $label }}</span>
                                    <span class="font-medium tabular-nums text-foreground">{{ $percent }}%</span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-muted" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                                    <div data-tone="{{ $tone }}" class="h-full rounded-full bg-[var(--tone)]" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    <x-ui.dialog id="vehicleDetailsModal" title="Vehicle details" size="lg">
        <div id="vehicleDetailsContent"></div>
        <x-slot:footer>
            <x-ui.button variant="outline" data-action="close-dialog">Close</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    @include('admin.compliance.partials.preview-dialog')

    @include('admin.compliance.partials.upload-document-modal')
@endsection

@push('scripts')
    <script>
        @include('admin.compliance.partials.page-script')

        const dateLabel = value => new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        function showDetails(kind, id) {
            kind === 'trailer' ? showTrailerDetails(id) : showVehicleDetails(id);
        }

        // One vehicle or trailer document card in the details dialog.
        function documentCard(doc, assetType, fileUrl) {
            const statusTone = doc.status === 'expired' ? 'danger' : doc.status === 'expiring' ? 'warning' : 'success';

            const statusText = doc.status === 'expired' ? 'Expired' :
                doc.status === 'expiring' ? `Expires in ${doc.days_until_expiry} days` :
                'Valid';

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
                            <div>
                                <dt class="text-muted-foreground">Expiry Date:</dt>
                                <dd class="font-medium text-foreground">${dateLabel(doc.expiry_date)}</dd>
                            </div>
                            ${doc.description ? `
                                <div>
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
                            <a href="/admin/compliance/documents/${Number(doc.id)}/${assetType}/download" class="btn" data-variant="ghost" data-size="icon-sm" title="Download" aria-label="Download">
                                ${icon('download')}
                            </a>
                            <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" data-action="view-document" data-url="${escapeHtml(fileUrl)}" title="View" aria-label="View">
                                ${icon('file-text')}
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        function missingDocumentsHtml(missing) {
            return missing && missing.length > 0 ? `
                <section>
                    <h4 class="mb-2 font-medium text-foreground">Missing/Expired Documents:</h4>
                    <ul class="space-y-2">
                        ${missing.map(doc => `
                            <li class="flex items-center gap-3 rounded-lg border p-2 text-sm text-foreground">
                                ${icon('circle-alert', 'size-4 shrink-0 text-warning')}
                                <span>${escapeHtml(doc)}</span>
                            </li>
                        `).join('')}
                    </ul>
                </section>
            ` : '';
        }

        function complianceHtml(asset) {
            const tone = complianceTone(asset.compliance_status);

            return `
                <section>
                    <h4 class="mb-2 font-medium text-foreground">Compliance Status</h4>
                    <div data-tone="${tone}" class="flex items-center justify-between gap-3 rounded-lg p-3 bg-[color-mix(in_oklch,var(--tone)_8%,var(--card))]">
                        <div class="text-sm">
                            <p class="font-medium text-foreground">${escapeHtml(asset.compliance_percentage)} Compliance</p>
                            <p class="text-muted-foreground">${Number(asset.compliant_docs)} of ${Number(asset.total_docs)} documents</p>
                        </div>
                        <span class="badge" data-tone="${tone}">${complianceLabel(asset.compliance_status)}</span>
                    </div>
                </section>
            `;
        }

        function openAssetDialog(title, loadingText) {
            document.getElementById('vehicleDetailsModal-title').textContent = title;
            document.getElementById('vehicleDetailsContent').innerHTML = loadingHtml(loadingText);
            document.getElementById('vehicleDetailsModal').showModal();
        }

        function showVehicleDetails(vehicleId) {
            const content = document.getElementById('vehicleDetailsContent');
            openAssetDialog('Vehicle details', 'Loading vehicle details...');

            fetch(`/admin/compliance/vehicles/${vehicleId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const vehicle = data.vehicle;
                        document.getElementById('vehicleDetailsModal-title').textContent =
                            `Vehicle #${vehicle.unit_no} Details`;

                        let documentsHtml = '';
                        if (vehicle.documents && vehicle.documents.length > 0) {
                            const assetType = 'vehicle';
                            documentsHtml = vehicle.documents.map(doc =>
                                documentCard(doc, assetType, `/admin/compliance/documents/${doc.id}/${assetType}/view`)).join('');
                        } else {
                            documentsHtml = '<p class="py-4 text-center text-muted-foreground">No documents uploaded yet</p>';
                        }

                        content.innerHTML = `
                            <div class="space-y-5">
                                <dl class="grid grid-cols-1 gap-4 rounded-lg bg-muted/50 p-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-muted-foreground">Make/Model</dt>
                                        <dd class="font-medium text-foreground">${escapeHtml(vehicle.make)} ${escapeHtml(vehicle.model)}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Year</dt>
                                        <dd class="font-medium text-foreground">${escapeHtml(vehicle.year)}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">VIN</dt>
                                        <dd class="break-all font-medium text-foreground">${escapeHtml(vehicle.vin)}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Odometer</dt>
                                        <dd class="font-medium text-foreground">${vehicle.odometer ? Number(vehicle.odometer).toLocaleString() + ' miles' : 'N/A'}</dd>
                                    </div>
                                </dl>

                                ${complianceHtml(vehicle)}

                                <section>
                                    <h4 class="mb-3 font-medium text-foreground">Documents</h4>
                                    <div class="space-y-3">
                                        ${documentsHtml}
                                    </div>
                                </section>

                                ${missingDocumentsHtml(vehicle.missing_documents)}
                            </div>
                        `;
                    } else {
                        content.innerHTML = errorHtml('Failed to load vehicle details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = errorHtml('Error loading vehicle details');
                });
        }

        function showTrailerDetails(trailerId) {
            const content = document.getElementById('vehicleDetailsContent');
            openAssetDialog('Trailer details', 'Loading trailer details...');

            fetch(`/admin/compliance/trailers/${trailerId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const trailer = data.trailer;
                        document.getElementById('vehicleDetailsModal-title').textContent =
                            `Trailer #${trailer.unit_no} Details`;

                        let documentsHtml = '';
                        if (trailer.documents && trailer.documents.length > 0) {
                            const assetType = 'trailer';
                            documentsHtml = trailer.documents.map(doc =>
                                documentCard(doc, assetType, `/admin/compliance/documents/${doc.id}/${assetType}/view`)).join('');
                        } else {
                            documentsHtml = '<p class="py-4 text-center text-muted-foreground">No documents uploaded yet</p>';
                        }

                        content.innerHTML = `
                            <div class="space-y-5">
                                <dl class="grid grid-cols-1 gap-4 rounded-lg bg-muted/50 p-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-muted-foreground">Make/Model</dt>
                                        <dd class="font-medium text-foreground">${escapeHtml(trailer.make)} ${escapeHtml(trailer.model)}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Year</dt>
                                        <dd class="font-medium text-foreground">${escapeHtml(trailer.year)}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">VIN</dt>
                                        <dd class="break-all font-medium text-foreground">${escapeHtml(trailer.vin)}</dd>
                                    </div>
                                </dl>

                                ${complianceHtml(trailer)}

                                <section>
                                    <h4 class="mb-3 font-medium text-foreground">Documents</h4>
                                    <div class="space-y-3">
                                        ${documentsHtml}
                                    </div>
                                </section>

                                ${missingDocumentsHtml(trailer.missing_documents)}
                            </div>
                        `;
                    } else {
                        content.innerHTML = errorHtml('Failed to load trailer details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = errorHtml('Error loading trailer details');
                });
        }
    </script>
@endpush
