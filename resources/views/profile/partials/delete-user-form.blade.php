@php($error = $errors->userDeletion->first('password'))
<x-ui.button variant="destructive" icon="user-round-x" data-dialog-open="confirm-user-deletion">{{ __('Close Account') }}</x-ui.button>

<x-ui.dialog id="confirm-user-deletion" size="sm" icon="triangle-alert" :title="__('Are you sure you want to close your account?')"
    :description="__('Your account and company will be deactivated and you will be logged out. Please enter your password to confirm.')">
    <form id="delete-user-form" method="post" action="{{ route('profile.destroy') }}">
        @csrf
        @method('delete')

        <x-ui.field :label="__('Password')" for="password" :data-invalid="$error ? 'true' : null">
            <x-ui.password id="password" name="password" :placeholder="__('Password')" autocomplete="current-password"
                :aria-invalid="$error ? 'true' : null" :aria-describedby="$error ? 'password-error' : null" />
            @if ($error)
                <p role="alert" id="password-error" class="text-sm text-destructive">{{ $error }}</p>
            @endif
        </x-ui.field>
    </form>
    <x-slot:footer>
        <x-ui.button variant="outline" data-dialog-close>{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit" form="delete-user-form" variant="destructive">{{ __('Close Account') }}</x-ui.button>
    </x-slot:footer>
</x-ui.dialog>

@if ($error)
    <script>document.getElementById('confirm-user-deletion').showModal();</script>
@endif
