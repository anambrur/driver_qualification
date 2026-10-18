@extends('layouts.platform')

@section('title', 'Live chat')

@section('content')
    <x-ui.page-header title="Live chat" description="The Tawk.to chat widget shown on public pages." />

    <form action="{{ route('platform.settings.tawk.update') }}" method="POST" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Chat widget">
            <div class="grid gap-6">
                <div>
                    <input type="hidden" name="tawk_enabled" value="0">
                    <x-ui.switch name="tawk_enabled" label="Enable Tawk.to chat" :checked="(bool) $setting->tawk_enabled"
                        description="When enabled, the widget appears on public-facing pages only." />
                </div>
                <x-ui.field label="Widget code" name="tawk_widget_code"
                    hint="Copy the complete code from Tawk.to Administration › Channels › Chat Widget. It is encrypted before it is stored and is never executed directly.">
                    <x-ui.textarea name="tawk_widget_code" rows="12" maxlength="20000" class="min-h-60 font-mono text-xs" spellcheck="false"
                        placeholder="Paste the complete Tawk.to widget code here…" :value="$setting->tawk_widget_code" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="outline" :href="route('platform.dashboard')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save settings</x-ui.button>
        </div>
    </form>
@endsection
