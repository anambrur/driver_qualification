@extends('layouts.site')

@section('title', $page->title)
@section('description', (string) $page->summary)

@php($contact = array_filter(\App\Support\Footer::content()['contact']))

@section('content')
    <x-site.page-hero :title="$page->title" :summary="$page->summary" />

    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-5 lg:px-8 lg:py-20">
        <div class="space-y-8 lg:col-span-2">
            <x-site.prose :html="$page->body" class="prose-base" />

            @if ($contact)
                <ul class="space-y-3">
                    @foreach (['email' => ['mail', 'Email'], 'phone' => ['phone', 'Phone'], 'address' => ['map-pin', 'Address']] as $key => [$icon, $label])
                        @isset($contact[$key])
                            <li class="flex items-start gap-4 rounded-2xl border border-border bg-card p-4">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><x-ui.icon :name="$icon" class="size-5" /></span>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $label }}</p>
                                    @if ($key === 'email')
                                        <a href="mailto:{{ $contact[$key] }}" class="font-medium break-words hover:text-primary">{{ $contact[$key] }}</a>
                                    @elseif ($key === 'phone')
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact[$key]) }}" class="font-medium hover:text-primary">{{ $contact[$key] }}</a>
                                    @else
                                        <address class="font-medium not-italic whitespace-pre-line">{{ $contact[$key] }}</address>
                                    @endif
                                </div>
                            </li>
                        @endisset
                    @endforeach
                </ul>
            @endif
        </div>

        <div id="contact-form" class="scroll-mt-28 lg:col-span-3">
            <div class="rounded-3xl border border-border bg-card p-6 shadow-sm sm:p-8">
                @if (session('contact_sent'))
                    <div class="py-10 text-center" role="status">
                        <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-success/10 text-success"><x-ui.icon name="check" class="size-7" /></span>
                        <h2 class="text-2xl font-bold">Thank you!</h2>
                        <p class="mt-2 text-muted-foreground">We have your message and will get back to you shortly.</p>
                    </div>
                @else
                    <h2 class="text-2xl font-bold">Send us a message</h2>
                    <p class="mt-1 text-sm text-muted-foreground">We usually reply within one business day.</p>

                    <form method="POST" action="{{ route('contact.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                        @csrf
                        {{-- Honeypot: people never see or fill it --}}
                        <div class="hidden" aria-hidden="true">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <x-ui.field label="Full name" name="name" required>
                            <x-ui.input name="name" required maxlength="100" autocomplete="name" />
                        </x-ui.field>
                        <x-ui.field label="Email" name="email" required>
                            <x-ui.input type="email" name="email" required maxlength="255" autocomplete="email" />
                        </x-ui.field>
                        <x-ui.field label="Company" name="company">
                            <x-ui.input name="company" maxlength="150" autocomplete="organization" />
                        </x-ui.field>
                        <x-ui.field label="Phone" name="phone">
                            <x-ui.input type="tel" name="phone" maxlength="50" autocomplete="tel" />
                        </x-ui.field>
                        <x-ui.field label="Message" name="message" required class="sm:col-span-2">
                            <x-ui.textarea name="message" rows="5" required maxlength="2000" />
                        </x-ui.field>
                        <div class="sm:col-span-2">
                            <x-ui.button type="submit" icon="send" class="w-full sm:w-auto">Send message</x-ui.button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
