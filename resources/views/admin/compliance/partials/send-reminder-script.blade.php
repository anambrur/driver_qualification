    function sendComplianceReminder({ confirmMessage, url, payload }) {
        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            showToast('Security token not found. Please refresh the page.', 'error');
            return;
        }

        const runSend = () => {
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(async response => {
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to send reminder');
                    }

                    return data;
                })
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Reminder sent successfully', 'success');
                    } else {
                        showToast(data.message || 'Failed to send reminder', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast(error.message || 'Error sending reminder', 'error');
                });
        };

        if (typeof showAppConfirm === 'function') {
            showAppConfirm(confirmMessage, {
                title: 'Send reminder?',
                confirmButtonText: 'Yes, send email',
            }).then((result) => {
                if (result.isConfirmed) {
                    runSend();
                }
            });
            return;
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        runSend();
    }

    function sendReminderEmail(entityId, docTypeId, assetType) {
        if (assetType === 'driver') {
            sendComplianceReminder({
                confirmMessage: 'Send reminder email to the driver?',
                url: @json(route('admin.compliance.driver.documents.send-reminder')),
                payload: {
                    driver_id: entityId,
                    document_type_id: docTypeId
                }
            });
            return;
        }

        sendComplianceReminder({
            confirmMessage: 'Send reminder email to the assigned driver?',
            url: @json(route('admin.compliance.documents.send-reminder')),
            payload: {
                asset_id: entityId,
                document_type_id: docTypeId,
                asset_type: assetType
            }
        });
    }
