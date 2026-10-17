@extends('layouts.main-layout')

@section('title', __('Profile'))

@section('content')
    <div class="mx-auto max-w-3xl p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="__('Profile')" description="Your name, email address and password." />

        <div class="space-y-6">
            <x-ui.card>
                @include('profile.partials.update-profile-information-form')
            </x-ui.card>

            <x-ui.card>
                @include('profile.partials.update-password-form')
            </x-ui.card>

            {{-- Company owners only (PO-17); ProfileController::destroy() enforces the same rule --}}
            @if ($canCloseAccount ?? false)
                <x-ui.card class="ring-destructive/30">
                    @include('profile.partials.delete-user-form')
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
