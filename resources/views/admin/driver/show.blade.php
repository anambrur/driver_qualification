@extends('layouts.main-layout')

@section('title', 'Driver Details')

@section('content')
    @php
        $driverFullName = trim(implode(' ', array_filter([$driver->first_name, $driver->middle_name, $driver->last_name, $driver->suffix])));
        $driverPhotoUrl = $driver->photo_file_id ? route('admin.driver.file', [$driver->id, 'photo']) : null;
        $tabs = [
            'overview' => 'Overview',
            'driver-info' => 'Driver information',
            'qualifications' => 'Qualifications',
            'files' => 'Files',
            'logs' => 'Activity',
        ];
    @endphp

    <div class="p-4 sm:p-6 lg:p-8">
        @include('admin.driver.show.header')

        <x-ui.tabs id="driver-tabs" :tabs="$tabs" class="mt-6">
            <x-ui.tab-panel tabs="driver-tabs" name="overview" selected class="mt-4">
                @include('admin.driver.show.overview')
            </x-ui.tab-panel>
            <x-ui.tab-panel tabs="driver-tabs" name="driver-info" class="mt-4">
                @include('admin.driver.show.information')
            </x-ui.tab-panel>
            <x-ui.tab-panel tabs="driver-tabs" name="qualifications" class="mt-4">
                @include('admin.driver.show.qualifications')
            </x-ui.tab-panel>
            <x-ui.tab-panel tabs="driver-tabs" name="files" class="mt-4">
                @include('admin.driver.show.files')
            </x-ui.tab-panel>
            <x-ui.tab-panel tabs="driver-tabs" name="logs" class="mt-4">
                @include('admin.driver.show.logs')
            </x-ui.tab-panel>
        </x-ui.tabs>
    </div>

    @if ($driver->status === 'pending')
        @can('drivers.hire')
            @include('admin.driver.show.hire-dialogs')
        @endcan
    @endif

    @include('admin.compliance.partials.driver-upload-document-modal')
@endsection

@push('scripts')
    <script>
        // Tabs: keep the open tab in the URL hash so links and reloads land on it.
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.getElementById('driver-tabs');
            const open = (name) => tabs.querySelector(`#driver-tabs-tab-${CSS.escape(name)}`)?.click();
            if (location.hash) open(location.hash.slice(1));
            tabs.addEventListener('click', (event) => {
                const tab = event.target.closest('[role="tab"]');
                if (tab) history.replaceState(null, '', '#' + tab.id.replace('driver-tabs-tab-', ''));
            });
        });

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        }

        function showToast(message, type = 'info') {
            window.notify(type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'), message);
        }

        function deleteComplianceDocument(documentId, documentName) {
            showAppConfirm(`Remove “${documentName}” for this driver?`, {
                title: 'Delete document?', icon: 'warning', confirmButtonText: 'Delete', variant: 'destructive',
            }).then((result) => {
                if (!result.isConfirmed) return;
                fetch(@json(route('admin.compliance.driver.documents.delete')), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ document_id: documentId })
                    })
                    .then(async (response) => {
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Failed to delete document');
                        return data;
                    })
                    .then((data) => {
                        showToast(data.message || 'Document deleted', 'success');
                        setTimeout(() => location.reload(), 800);
                    })
                    .catch((error) => showToast(error.message || 'Error deleting document', 'error'));
            });
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;
            const data = button.dataset;

            switch (data.action) {
                case 'upload-document':
                    openUploadModal(Number(data.id), Number(data.typeId), data.assetType);
                    break;
                case 'send-reminder':
                    sendReminderEmail(Number(data.id), Number(data.typeId), data.assetType);
                    break;
                case 'delete-document':
                    deleteComplianceDocument(data.documentId, data.documentName);
                    break;
            }
        });
    </script>
    @if ($driver->status === 'pending')
        @can('drivers.hire')
            @include('admin.driver.show.hire-script')
        @endcan
    @endif
    <script>
        @include('admin.compliance.partials.send-reminder-script')
    </script>
@endpush
