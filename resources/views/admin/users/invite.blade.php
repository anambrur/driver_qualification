@extends('layouts.main-layout')

@section('title', 'Invite User')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Invite User" description="We email an invitation link. The new user sets their own name and password.">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Users', 'href' => route('users.index')], ['label' => 'Invite User']]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <form action="{{ route('users.invitations.store') }}" method="POST" class="max-w-3xl space-y-6">
            @csrf

            <x-ui.card title="Invitation" :description="'The link expires after '.\App\Services\Users\InvitationService::EXPIRES_IN_DAYS.' days. You can resend or revoke it from the users list.'">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Email" for="email" name="email" required>
                        <x-ui.input type="email" name="email" id="email" required placeholder="john@example.com" />
                    </x-ui.field>
                    <x-ui.field label="Role" for="role_id" name="role_id" required>
                        <x-ui.select name="role_id" id="role_id" required :options="$roles->pluck('name', 'id')" placeholder="Select a role" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="outline" :href="route('users.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="send">Send Invitation</x-ui.button>
            </div>
        </form>
    </div>
@endsection
