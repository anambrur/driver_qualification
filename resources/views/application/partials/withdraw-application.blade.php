{{-- Lets the applicant withdraw their own application (APP-06). Needs $company and $driver. --}}
<form method="POST" action="{{ route('public.application.withdraw', ['slug' => $company->slug, 'driver_id' => $driver->id]) }}"
    class="mt-6" data-confirm-withdraw>
    @csrf
    <button type="submit"
        class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
        <i class="fas fa-times-circle mr-2"></i>
        Withdraw application
    </button>
</form>

@once
    @push('scripts')
        <script>
            document.addEventListener('submit', function(e) {
                const form = e.target.closest('form[data-confirm-withdraw]');
                if (!form || form.dataset.confirmed) return;

                e.preventDefault();
                const message = 'Withdraw your application? The company will no longer review it. You can apply again later with the same phone number.';
                const submit = () => {
                    form.dataset.confirmed = '1';
                    form.submit();
                };

                if (typeof Swal === 'undefined') {
                    if (confirm(message)) submit();
                    return;
                }

                Swal.fire({
                    title: 'Withdraw application?',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Withdraw',
                    cancelButtonText: 'Keep my application',
                }).then((result) => {
                    if (result.isConfirmed) submit();
                });
            });
        </script>
    @endpush
@endonce
