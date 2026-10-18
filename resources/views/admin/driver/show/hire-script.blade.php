<script>
    (() => {
        const statusUrl = @json(route('admin.driver.hire-status', $driver->id));
        const forms = {
            hire: document.getElementById('hireForm'),
            reject: document.getElementById('notHireForm'),
        };

        const summary = (rows) => rows
            .map(([label, value]) => `<strong>${escapeHtml(label)}:</strong> ${escapeHtml(value || 'None')}`)
            .join('<br>');

        // One popup at a time: close the form dialog while SweetAlert confirms, reopen it on cancel.
        const confirmFrom = (dialogId, options, onConfirm) => {
            const dialog = document.getElementById(dialogId);
            dialog.close();
            Swal.fire(options).then((result) => {
                if (result.isConfirmed) onConfirm();
                else dialog.showModal();
            });
        };

        forms.hire.addEventListener('submit', (event) => {
            event.preventDefault();
            const data = new FormData(forms.hire);
            confirmFrom('hire-dialog', {
                title: 'Confirm hire?',
                html: summary([['Date hired', data.get('hire_date')], ['Hazmat', data.get('hazmat')], ['LCV certificate', data.get('lcv_certificate')]]),
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Hire driver',
                reverseButtons: true,
            }, () => updateDriverStatus('hire', data, 'hire-dialog'));
        });

        forms.reject.addEventListener('submit', (event) => {
            event.preventDefault();
            const data = new FormData(forms.reject);
            confirmFrom('not-hire-dialog', {
                title: 'Confirm not hiring?',
                html: summary([['Reason', forms.reject.querySelector('[name="rejection_reason"] option:checked').textContent], ['Additional info', data.get('additional_info')]]),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Do not hire',
                reverseButtons: true,
                customClass: { confirmButton: 'swal2-deny' },
            }, () => updateDriverStatus('reject', data, 'not-hire-dialog'));
        });

        function updateDriverStatus(action, formData, dialogId) {
            Swal.fire({ title: 'Processing…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

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
                    Swal.fire({ title: 'Done', text: data.message, icon: 'success' }).then(() => window.location.reload());
                })
                // Back to the form so the user can correct it.
                .catch((error) => Swal.fire({ title: 'Error', text: error.message, icon: 'error' })
                    .then(() => document.getElementById(dialogId).showModal()));
        }
    })();
</script>
