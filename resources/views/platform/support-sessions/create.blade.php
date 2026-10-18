@extends('layouts.platform')

@section('title', 'Request support access')

@section('content')
    <x-ui.page-header title="Request support access" description="The only way into a company's data. Every request in a session is audited.">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Support sessions', 'href' => route('platform.support-sessions.index')], ['label' => 'Request access']]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <form method="POST" action="{{ route('platform.support-sessions.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.alert tone="info" title="Two approvals are needed">
            The company owner is emailed your reason and must approve, and so must a second Platform Owner.
            Approval stays valid for {{ \App\Models\PlatformSupportSession::REQUEST_WINDOW_HOURS }} hours; a started session lasts at most
            {{ settings('support_session_max_minutes', 240) }} minutes.
        </x-ui.alert>

        <x-ui.card title="Request">
            <div class="grid gap-5">
                <x-ui.field label="Company" name="company_id" required hint="Active companies only.">
                    <x-ui.select name="company_id" required :options="['' => 'Choose…'] + $companies->pluck('company_name', 'id')->all()" />
                </x-ui.field>
                <x-ui.radio-group name="scope" legend="Access" required value="read" class="[&>div]:flex-col"
                    :options="['read' => 'Read only', 'read_write' => 'Read and write (no billing, users, roles, or deleting drivers and documents)']" />
                <x-ui.field label="Reason" name="reason" required hint="The owner sees this. At least 10 characters.">
                    <x-ui.textarea name="reason" rows="4" required minlength="10" maxlength="1000" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="outline" :href="route('platform.support-sessions.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="send">Send request</x-ui.button>
        </div>
    </form>
@endsection
