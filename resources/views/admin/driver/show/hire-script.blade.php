<script>
    (() => {
        const statusUrl = @json(route('admin.driver.hire-status', $driver->id));
        const forms = {
            hire: document.getElementById('hireForm'),
            reject: document.getElementById('notHireForm'),
        };

        // Plain text: the confirm dialog keeps the line breaks.
        const summary = (rows) => rows.map(([label, value]) => `${label}: ${value || 'None'}`).join('\n');

        // One dialog at a time: close the form dialog while confirming, reopen it on cancel.
        const confirmFrom = (dialogId, [text, options], onConfirm) => {
            const dialog = document.getElementById(dialogId);
            dialog.close();
            showAppConfirm(text, options).then((result) => {
                if (result.isConfirmed) onConfirm();
                else dialog.showModal();
            });
        };

        forms.hire.addEventListener('submit', (event) => {
            event.preventDefault();
            const data = new FormData(forms.hire);
            confirmFrom('hire-dialog', [
                summary([['Date hired', data.get('hire_date')], ['Hazmat', data.get('hazmat')], ['LCV certificate', data.get('lcv_certificate')]]),
                { title: 'Confirm hire?', icon: 'question', confirmButtonText: 'Hire driver' },
            ], () => updateDriverStatus('hire', data, 'hire-dialog'));
        });

        forms.reject.addEventListener('submit', (event) => {
            event.preventDefault();
            const data = new FormData(forms.reject);
            confirmFrom('not-hire-dialog', [
                summary([['Reason', forms.reject.querySelector('[name="rejection_reason"] option:checked').textContent], ['Additional info', data.get('additional_info')]]),
                { title: 'Confirm not hiring?', icon: 'warning', confirmButtonText: 'Do not hire', variant: 'destructive' },
            ], () => updateDriverStatus('reject', data, 'not-hire-dialog'));
        });

        function updateDriverStatus(action, formData, dialogId) {
            window.notify('info', 'Processing…');

            fetch(statusUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                    body: JSON.stringify({ ...Object.fromEntries(formData), action }),
                })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) throw new Error(data.message || 'Something went wrong. Please try again.');
                    return data;
                })
                .then((data) => {
                    showAppAlert(data.message, 'success', 'Done').then(() => window.location.reload());
                })
                // Back to the form so the user can correct it.
                .catch((error) => showAppAlert(error.message, 'error', 'Error')
                    .then(() => document.getElementById(dialogId).showModal()));
        }
    })();
</script>
