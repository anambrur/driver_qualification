{{-- The inbox replies go to, for a company or the platform: $owner has reply_to_email / pending_reply_to_email. --}}
<x-ui.card title="Email for replies" :description="$description">
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($owner->reply_to_email)
                <x-ui.badge tone="success" dot>Confirmed</x-ui.badge>
                <span class="font-medium">{{ $owner->reply_to_email }}</span>
            @else
                <x-ui.badge tone="neutral" dot>Not set</x-ui.badge>
                <span class="text-muted-foreground">{{ $fallback }}</span>
            @endif
        </div>

        @if ($owner->pending_reply_to_email)
            <x-ui.alert tone="warning">
                Waiting for confirmation: open the link we sent to <strong>{{ $owner->pending_reply_to_email }}</strong>.
            </x-ui.alert>
        @endif

        <form action="{{ $storeUrl }}" method="POST" class="grid gap-4">
            @csrf
            <x-ui.field label="Email" for="reply_to_email" name="reply_to_email" required>
                <x-ui.input type="email" name="reply_to_email" id="reply_to_email" required maxlength="255" placeholder="office@yourcompany.com"
                    :value="old('reply_to_email', $owner->pending_reply_to_email ?? $owner->reply_to_email)" />
            </x-ui.field>
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit" icon="mail-check">
                    {{ $owner->pending_reply_to_email ? 'Send the link again' : 'Save and confirm' }}
                </x-ui.button>
            </div>
        </form>

        @if ($owner->reply_to_email || $owner->pending_reply_to_email)
            <form action="{{ $destroyUrl }}" method="POST" data-confirm="Stop sending replies to this email?" data-confirm-button="Remove">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="outline" size="sm" icon="trash-2">Remove</x-ui.button>
            </form>
        @endif
    </div>
</x-ui.card>
