{{--
    Shared by the driver and fleet compliance dashboards, included inside their <script>.
    The page defines showDetails(kind, id) for data-action="show-details".
--}}
    // Lucide icons for markup built in JS (the same SVGs the Blade icon component renders).
    const lucide = {{ \Illuminate\Support\Js::from(collect(['circle-alert', 'download', 'eye', 'file-text', 'loader-circle', 'triangle-alert'])->mapWithKeys(fn ($n) => [$n => svg('lucide-'.$n, 'lucide-icon')->toHtml()])) }};
    const icon = (name, cls = 'size-4') => lucide[name].replace('class="lucide-icon"', `class="inline-block align-[-0.125em] ${cls}"`);

    // Driver, vehicle and trailer data come from public applicants and tenant users: never put it in HTML unescaped.
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Badge tone and label for a driver/asset compliance status.
    const complianceTone = status => status === 'warning' ? 'warning' : status === 'danger' ? 'danger' : 'success';
    const complianceLabel = status => status === 'warning' ? 'Warning' : status === 'danger' ? 'Critical' : 'Compliant';

    const loadingHtml = text => `
        <div class="flex flex-col items-center justify-center gap-2 py-12 text-muted-foreground">
            ${icon('loader-circle', 'size-6 animate-spin text-primary')}
            <p>${text}</p>
        </div>`;

    const errorHtml = text => `
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            ${icon('circle-alert', 'size-8 text-destructive')}
            <p class="text-foreground">${text}</p>
        </div>`;

    function previewImage(fileUrl, title) {
        const dialog = document.getElementById('imagePreviewModal');
        const image = document.getElementById('imagePreviewImage');
        document.getElementById('imagePreviewModal-title').textContent = title;
        image.src = fileUrl;
        image.alt = title;
        document.getElementById('imagePreviewDownload').href = fileUrl;
        dialog.showModal();
    }

    function closeImagePreview() {
        document.getElementById('imagePreviewModal').close();
    }

    document.getElementById('imagePreviewModal')?.addEventListener('close', () => {
        document.getElementById('imagePreviewImage').removeAttribute('src');
    });

    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag && metaTag.content) {
            return metaTag.content;
        }

        const csrfInput = document.querySelector('input[name="_token"]');
        if (csrfInput && csrfInput.value) {
            return csrfInput.value;
        }

        if (window.Laravel && window.Laravel.csrfToken) {
            return window.Laravel.csrfToken;
        }

        console.error('CSRF token not found');
        return '';
    }

    function showToast(message, type = 'info') {
        window.notify(type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'), message);
    }

    @include('admin.compliance.partials.send-reminder-script')

    document.getElementById('refresh-dashboard')?.addEventListener('click', function() {
        showToast('Refreshing dashboard...', 'info');
        setTimeout(() => {
            location.reload();
        }, 500);
    });

    document.addEventListener('click', function(e) {
        const button = e.target.closest('[data-action]');
        if (!button) {
            return;
        }

        const data = button.dataset;
        const typeId = data.typeId ? Number(data.typeId) : null;

        switch (data.action) {
            case 'show-details':
                showDetails(data.kind, Number(data.id));
                break;
            case 'upload-document':
                openUploadModal(Number(data.id), typeId, data.assetType);
                break;
            case 'send-reminder':
                sendReminderEmail(Number(data.id), typeId, data.assetType);
                break;
            case 'preview-document':
                previewImage(data.url, data.title);
                break;
            case 'view-document':
                window.open(data.url, '_blank', 'noopener');
                break;
            case 'close-dialog':
                button.closest('dialog')?.close();
                break;
        }
    });
