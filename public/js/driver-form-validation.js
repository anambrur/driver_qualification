/**
 * Shared driver form helpers: photo preview, SSN/phone formatters,
 * section-aware client validation for create / edit / public step1.
 */
(function (window) {
    'use strict';

    function setupFilePreview(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const imagePreview = document.getElementById(inputId + '_preview');
        const previewImg = document.getElementById(inputId + '_preview_img');
        const filenameEl = document.getElementById(inputId + '_filename');
        const removeBtn = document.getElementById(inputId + '_remove_btn');
        const existingEl = document.getElementById(inputId + '_existing');

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                showFieldError(input, 'Photo must be 2MB or smaller.');
                input.value = '';
                return;
            }

            if (!file.type.startsWith('image/')) {
                showFieldError(input, 'Please select an image file.');
                input.value = '';
                return;
            }

            clearFieldError(input);

            if (imagePreview && previewImg) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    imagePreview.classList.remove('hidden');
                    if (existingEl) existingEl.classList.add('hidden');
                    if (filenameEl) filenameEl.textContent = file.name;
                };
                reader.readAsDataURL(file);
            }
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                input.value = '';
                if (imagePreview) imagePreview.classList.add('hidden');
                if (previewImg) previewImg.src = '';
                if (filenameEl) filenameEl.textContent = '';
                if (existingEl) existingEl.classList.remove('hidden');
            });
        }
    }

    function formatSsnInputs() {
        document.querySelectorAll('#ssn, input[name="ssn"]').forEach(function (ssnInput) {
            ssnInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '').slice(0, 9);
                if (value.length > 5) {
                    value = value.slice(0, 3) + '-' + value.slice(3, 5) + '-' + value.slice(5);
                } else if (value.length > 3) {
                    value = value.slice(0, 3) + '-' + value.slice(3);
                }
                e.target.value = value;
            });
        });
    }

    function formatPhoneInputs() {
        document.querySelectorAll('input[type="tel"]').forEach(function (input) {
            input.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '').slice(0, 10);
                if (value.length > 6) {
                    value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6);
                } else if (value.length > 3) {
                    value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
                }
                e.target.value = value;
            });
        });
    }

    function showFieldError(el, message) {
        if (!el) return;
        el.classList.add('border-red-500');
        el.setAttribute('aria-invalid', 'true');

        let err = el.parentElement && el.parentElement.querySelector('.js-field-error');
        if (!err && el.parentElement) {
            err = document.createElement('p');
            err.className = 'js-field-error mt-1 text-sm text-red-500';
            el.parentElement.appendChild(err);
        }
        if (err) err.textContent = message;
    }

    function clearFieldError(el) {
        if (!el) return;
        el.classList.remove('border-red-500');
        el.removeAttribute('aria-invalid');
        const err = el.parentElement && el.parentElement.querySelector('.js-field-error');
        if (err) err.remove();
    }

    function ageFromDob(dobValue) {
        const dob = new Date(dobValue);
        if (Number.isNaN(dob.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        return age;
    }

    function validateDriverForm(form) {
        let firstInvalid = null;
        let count = 0;

        form.querySelectorAll('.js-field-error').forEach(function (n) { n.remove(); });
        form.querySelectorAll('.border-red-500').forEach(function (n) {
            n.classList.remove('border-red-500');
        });

        form.querySelectorAll('[required]').forEach(function (el) {
            if (el.disabled) return;
            const type = (el.type || '').toLowerCase();
            let empty = false;
            if (type === 'checkbox' || type === 'radio') {
                const name = el.name;
                const group = form.querySelectorAll('[name="' + name + '"]');
                empty = !Array.from(group).some(function (g) { return g.checked; });
            } else {
                empty = !String(el.value || '').trim();
            }
            if (empty) {
                showFieldError(el, 'This field is required.');
                count++;
                if (!firstInvalid) firstInvalid = el;
            }
        });

        const email = form.querySelector('#email, input[name="email"]');
        if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            showFieldError(email, 'Enter a valid email address.');
            count++;
            if (!firstInvalid) firstInvalid = email;
        }

        const dob = form.querySelector('#date_of_birth, input[name="date_of_birth"]');
        if (dob && dob.value) {
            const age = ageFromDob(dob.value);
            if (age !== null && age < 18) {
                showFieldError(dob, 'Driver must be at least 18 years old.');
                count++;
                if (!firstInvalid) firstInvalid = dob;
            }
        }

        const licenseNumber = form.querySelector('#license_number');
        const repeatLicense = form.querySelector('#repeat_license_number');
        if (licenseNumber && repeatLicense && licenseNumber.value && repeatLicense.value
            && licenseNumber.value !== repeatLicense.value) {
            showFieldError(repeatLicense, 'License numbers do not match.');
            count++;
            if (!firstInvalid) firstInvalid = repeatLicense;
        }

        const issued = form.querySelector('#license_issued');
        const expires = form.querySelector('#license_expires');
        if (issued && expires && issued.value && expires.value) {
            if (new Date(issued.value) >= new Date(expires.value)) {
                showFieldError(expires, 'Expiration must be after the issued date.');
                count++;
                if (!firstInvalid) firstInvalid = expires;
            }
        }

        const accidentYes = form.querySelector('input[name="accident"][value="yes"]');
        if (accidentYes && accidentYes.checked) {
            const dates = form.querySelectorAll('input[name="accident_date[]"]');
            const hasRow = Array.from(dates).some(function (d) { return String(d.value || '').trim(); });
            if (!hasRow) {
                showFieldError(accidentYes, 'Add at least one accident record or select No.');
                count++;
                if (!firstInvalid) firstInvalid = accidentYes;
            }
        }

        const violationYes = form.querySelector('input[name="violation"][value="yes"]');
        if (violationYes && violationYes.checked) {
            const dates = form.querySelectorAll('input[name="violation_date[]"]');
            const hasRow = Array.from(dates).some(function (d) { return String(d.value || '').trim(); });
            if (!hasRow) {
                showFieldError(violationYes, 'Add at least one violation record or select No.');
                count++;
                if (!firstInvalid) firstInvalid = violationYes;
            }
        }

        return { valid: count === 0, firstInvalid: firstInvalid, count: count };
    }

    function updateSummary(form, count) {
        let bar = document.getElementById('driver-validation-summary');
        if (count <= 0) {
            if (bar) bar.classList.add('hidden');
            return;
        }
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'driver-validation-summary';
            bar.className = 'fixed bottom-20 left-1/2 z-40 -translate-x-1/2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-lg';
            document.body.appendChild(bar);
        }
        bar.textContent = count + ' field' + (count === 1 ? '' : 's') + ' need attention';
        bar.classList.remove('hidden');
    }

    function initDriverFormValidation(formId) {
        const form = document.getElementById(formId || 'driverForm');
        if (!form) return;

        setupFilePreview('photo');
        formatSsnInputs();
        formatPhoneInputs();

        form.addEventListener('submit', function (e) {
            const result = validateDriverForm(form);
            updateSummary(form, result.count);
            if (!result.valid) {
                e.preventDefault();
                if (result.firstInvalid) {
                    result.firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    result.firstInvalid.focus({ preventScroll: true });
                }
            }
        });

        form.addEventListener('input', function (e) {
            if (e.target && e.target.matches('input, select, textarea')) {
                clearFieldError(e.target);
            }
        });
        form.addEventListener('change', function (e) {
            if (e.target && e.target.matches('input, select, textarea')) {
                clearFieldError(e.target);
            }
        });

        // Scroll to first server-side error
        const serverError = form.querySelector('.text-red-500, .text-error-500, .border-red-500');
        if (serverError) {
            serverError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function initSectionJump() {
        document.querySelectorAll('#driver-section-jump, #driver-section-jump-mobile, .driver-section-jump').forEach(function (select) {
            select.addEventListener('change', function () {
                const id = select.value;
                if (!id) return;
                const el = document.getElementById(id);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    window.DriverFormValidation = {
        setupFilePreview: setupFilePreview,
        init: initDriverFormValidation,
        initSectionJump: initSectionJump,
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('driverForm')) {
            initDriverFormValidation('driverForm');
            initSectionJump();
        }
    });
})(window);
