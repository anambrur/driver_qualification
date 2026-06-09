    function sendComplianceReminder({ confirmMessage, url, payload }) {
        if (!confirm(confirmMessage)) {
            return;
        }

        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            showToast('Security token not found. Please refresh the page.', 'error');
            return;
        }

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
