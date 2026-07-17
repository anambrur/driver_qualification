@extends('layouts.main-layout')

@section('title', 'Tawk.to Chat Settings')

@section('content')
    <div class="p-4 mx-auto max-w-3xl md:p-6">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Tawk.to Chat Settings</h2>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Configure the live chat widget shown on public pages</p>
            </div>

            <nav x-data="{}" class="hidden sm:block">
                <ol class="flex items-center gap-2">
                    <li>
                        <a class="font-medium text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400"
                            href="{{ route('admin.dashboard') }}">Dashboard /</a>
                    </li>
                    <li class="font-medium text-brand-500 dark:text-brand-400">Tawk.to Chat</li>
                </ol>
            </nav>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <div class="text-red-700 dark:text-red-400">
                        Please check the form below for errors.
                    </div>
                </div>
            </div>
        @endif

        <form action="{{ route('admin.settings.tawk.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="px-5 py-4 sm:px-6 sm:py-5">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-comments mr-2"></i>Chat Widget Configuration
                    </h3>
                </div>

                <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                    <!-- Enable Toggle -->
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <label for="tawk_enabled" class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Enable Tawk.to Chat
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                When enabled, the chat widget appears on public-facing pages only.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="tawk_enabled" value="0">
                            <input type="checkbox" id="tawk_enabled" name="tawk_enabled" value="1"
                                class="sr-only peer"
                                {{ old('tawk_enabled', $setting->tawk_enabled) ? 'checked' : '' }}>
                            <div
                                class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 dark:peer-focus:ring-brand-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-500">
                            </div>
                        </label>
                    </div>

                    <!-- Widget Code -->
                    <div>
                        <label for="tawk_widget_code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Tawk.to Widget Code
                        </label>
                        <textarea id="tawk_widget_code" name="tawk_widget_code" rows="14"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 font-mono text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="Paste the complete Tawk.to widget code here...">{{ old('tawk_widget_code', $setting->tawk_widget_code) }}</textarea>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                            Copy the complete widget code from Tawk.to Administration &gt; Channels &gt; Chat Widget.
                            It is encrypted before database storage and is never executed directly.
                        </p>
                        @error('tawk_widget_code')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
@endsection
