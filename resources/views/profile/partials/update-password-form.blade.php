{{-- Errors come from the updatePassword bag, so each field renders its own message --}}
<form method="post" action="{{ route('password.update') }}" class="grid gap-6">
    @csrf
    @method('put')

    @foreach ([
        'current_password' => [__('Current Password'), 'current-password'],
        'password' => [__('New Password'), 'new-password'],
        'password_confirmation' => [__('Confirm Password'), 'new-password'],
    ] as $field => [$label, $autocomplete])
        @php($error = $errors->updatePassword->first($field))
        <x-ui.field :label="$label" :for="'update_password_'.$field" :data-invalid="$error ? 'true' : null">
            <x-ui.password :name="$field" :id="'update_password_'.$field" :autocomplete="$autocomplete"
                :aria-invalid="$error ? 'true' : null" :aria-describedby="$error ? 'update_password_'.$field.'-error' : null" />
            @if ($error)
                <p role="alert" id="update_password_{{ $field }}-error" class="text-sm text-destructive">{{ $error }}</p>
            @endif
        </x-ui.field>
    @endforeach

    <div class="flex items-center gap-4">
        <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>

        @if (session('status') === 'password-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                class="text-sm text-muted-foreground">{{ __('Saved.') }}</p>
        @endif
    </div>
</form>
