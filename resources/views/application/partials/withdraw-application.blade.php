{{-- Lets the applicant withdraw their own application (APP-06). Needs $company and $driver.
     The layout's <x-ui.confirm-dialog> asks first (data-confirm, resources/js/app.js). --}}
<form method="POST" action="{{ route('public.application.withdraw', ['slug' => $company->slug, 'driver_id' => $driver->id]) }}"
    class="mt-6" data-confirm="Withdraw application?"
    data-confirm-text="Withdraw your application? The company will no longer review it. You can apply again later with the same phone number."
    data-confirm-button="Withdraw" data-confirm-cancel="Keep my application">
    @csrf
    <x-ui.button type="submit" variant="outline" icon="circle-x" class="w-full text-destructive hover:text-destructive">Withdraw application</x-ui.button>
</form>
