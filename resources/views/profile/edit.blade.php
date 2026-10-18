@extends('layouts.main-layout')

@section('title', __('Profile'))

@section('content')
    <div class="mx-auto max-w-3xl p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="__('Profile')" description="Your name, email address and password." />

        <div class="space-y-6">
            <x-ui.card :title="__('Profile Information')" :description="__('Update your account\'s profile information and email address.')">
                @include('profile.partials.update-profile-information-form')
            </x-ui.card>

            <x-ui.card :title="__('Update Password')" :description="__('Ensure your account is using a long, random password to stay secure.')">
                @include('profile.partials.update-password-form')
            </x-ui.card>

            {{-- Company owners only (PO-17); ProfileController::destroy() enforces the same rule --}}
            @if ($canCloseAccount ?? false)
                <x-ui.card :title="__('Close Account')" class="ring-destructive/30"
                    :description="__('Closing your account deactivates it and your company: you can no longer log in and applicants can no longer apply. Your drivers, vehicles and other records are kept. Contact support to reopen it.')">
                    @include('profile.partials.delete-user-form')
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
