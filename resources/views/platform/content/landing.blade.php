@extends('layouts.platform')

@section('title', 'Landing page')

@use('App\Support\Landing')

@php
    $icons = collect(Landing::ICONS)->mapWithKeys(fn ($icon) => [$icon => \Illuminate\Support\Str::headline($icon)])->all();
    $list = fn (string $key) => old($key) ?? data_get($landing, $key, []);
    $bullets = old('hero.bullets', $landing['hero']['bullets']);
@endphp

@section('content')
    <x-ui.page-header title="Landing page" description="The text and lists on the public homepage. Switch a section off to hide it and its menu link.">
        <x-slot:actions>
            <x-ui.button variant="outline" icon="external-link" :href="route('home')" target="_blank" rel="noopener">View homepage</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('platform.content.landing.update') }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Hero" description="The first thing visitors see. The highlight is shown in brand colour after the title.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Badge" name="hero[badge]">
                    <x-ui.input name="hero[badge]" :value="$landing['hero']['badge']" maxlength="60" />
                </x-ui.field>
                <div class="hidden sm:block"></div>
                <x-ui.field label="Title" name="hero[title]" required>
                    <x-ui.input name="hero[title]" :value="$landing['hero']['title']" required maxlength="120" />
                </x-ui.field>
                <x-ui.field label="Highlight" name="hero[highlight]">
                    <x-ui.input name="hero[highlight]" :value="$landing['hero']['highlight']" maxlength="80" />
                </x-ui.field>
                <x-ui.field label="Subtitle" name="hero[subtitle]" class="sm:col-span-2">
                    <x-ui.textarea name="hero[subtitle]" rows="2" maxlength="400" :value="$landing['hero']['subtitle']" />
                </x-ui.field>
                <div class="grid gap-4 sm:col-span-2 sm:grid-cols-3">
                    @foreach (range(0, 2) as $i)
                        <x-ui.field :label="'Check mark '.($i + 1)" :name="'hero[bullets]['.$i.']'">
                            <x-ui.input :name="'hero[bullets]['.$i.']'" :value="$bullets[$i] ?? ''" maxlength="40" />
                        </x-ui.field>
                    @endforeach
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Stats bar" description="Up to four numbers under the hero.">
            <div class="grid gap-4">
                <x-ui.switch name="stats[enabled]" label="Show the stats bar" :checked="(bool) $landing['stats']['enabled']" />
                @include('platform.content.partials.rows', [
                    'name' => 'stats[items]', 'rows' => $list('stats.items'), 'max' => Landing::LIMITS['stats'],
                    'label' => 'Stat', 'empty' => 'No stats. The bar is hidden.',
                    'blank' => ['value' => '', 'label' => ''],
                    'fields' => ['value' => ['label' => 'Value', 'max' => 20], 'label' => ['label' => 'Label', 'max' => 60]],
                ])
            </div>
        </x-ui.card>

        <x-ui.card title="Features" description="The feature cards.">
            <div class="grid gap-4">
                <x-ui.switch name="features[enabled]" label="Show features" :checked="(bool) $landing['features']['enabled']" />
                @include('platform.content.partials.heading', ['section' => 'features', 'subtitle' => true])
                @include('platform.content.partials.rows', [
                    'name' => 'features[items]', 'rows' => $list('features.items'), 'max' => Landing::LIMITS['features'],
                    'label' => 'Feature', 'empty' => 'No features. The section is hidden.',
                    'blank' => ['icon' => Landing::ICONS[0], 'color' => 'brand', 'title' => '', 'text' => ''],
                    'fields' => [
                        'icon' => ['label' => 'Icon', 'type' => 'select', 'options' => $icons],
                        'color' => ['label' => 'Colour', 'type' => 'select', 'options' => Landing::COLORS],
                        'title' => ['label' => 'Title', 'max' => 80, 'wide' => true],
                        'text' => ['label' => 'Text', 'type' => 'textarea', 'max' => 300, 'wide' => true],
                    ],
                ])
            </div>
        </x-ui.card>

        <x-ui.card title="How it works" description="The numbered steps.">
            <div class="grid gap-4">
                <x-ui.switch name="steps[enabled]" label="Show how it works" :checked="(bool) $landing['steps']['enabled']" />
                @include('platform.content.partials.heading', ['section' => 'steps', 'subtitle' => true])
                @include('platform.content.partials.rows', [
                    'name' => 'steps[items]', 'rows' => $list('steps.items'), 'max' => Landing::LIMITS['steps'],
                    'label' => 'Step', 'empty' => 'No steps. The section is hidden.',
                    'blank' => ['icon' => Landing::ICONS[0], 'title' => '', 'text' => ''],
                    'fields' => [
                        'icon' => ['label' => 'Icon', 'type' => 'select', 'options' => $icons],
                        'title' => ['label' => 'Title', 'max' => 80],
                        'text' => ['label' => 'Text', 'type' => 'textarea', 'max' => 300, 'wide' => true],
                    ],
                ])
            </div>
        </x-ui.card>

        <x-ui.card title="Pricing" description="Section heading only. The plan cards come from Plans: public, active plans with a current price.">
            <div class="grid gap-4">
                <x-ui.switch name="pricing[enabled]" label="Show pricing" :checked="(bool) $landing['pricing']['enabled']" />
                @include('platform.content.partials.heading', ['section' => 'pricing', 'subtitle' => true])
            </div>
        </x-ui.card>

        <x-ui.card title="Testimonials" description="Section heading only. The quotes come from Testimonials.">
            <x-slot:action>
                <x-ui.button variant="outline" size="sm" :href="route('platform.content.testimonials.index')">Manage testimonials</x-ui.button>
            </x-slot:action>
            <div class="grid gap-4">
                <x-ui.switch name="testimonials[enabled]" label="Show testimonials" :checked="(bool) $landing['testimonials']['enabled']" />
                @include('platform.content.partials.heading', ['section' => 'testimonials', 'subtitle' => false])
            </div>
        </x-ui.card>

        <x-ui.card title="FAQ" description="Section heading only. The questions come from FAQs.">
            <x-slot:action>
                <x-ui.button variant="outline" size="sm" :href="route('platform.content.faqs.index')">Manage FAQs</x-ui.button>
            </x-slot:action>
            <div class="grid gap-4">
                <x-ui.switch name="faq[enabled]" label="Show the FAQ" :checked="(bool) $landing['faq']['enabled']" />
                @include('platform.content.partials.heading', ['section' => 'faq', 'subtitle' => false])
            </div>
        </x-ui.card>

        <x-ui.card title="Call to action" description="The banner before the FAQ.">
            <div class="grid gap-4">
                <x-ui.switch name="cta[enabled]" label="Show the banner" :checked="(bool) $landing['cta']['enabled']" />
                <x-ui.field label="Title" name="cta[title]" required>
                    <x-ui.input name="cta[title]" :value="$landing['cta']['title']" required maxlength="120" />
                </x-ui.field>
                <x-ui.field label="Text" name="cta[text]">
                    <x-ui.textarea name="cta[text]" rows="2" maxlength="400" :value="$landing['cta']['text']" />
                </x-ui.field>
                <x-ui.switch name="cta[demo]" label="Show the Schedule a demo button" description="Requests land in Demo requests and are emailed to the support address." :checked="(bool) $landing['cta']['demo']" />
            </div>
        </x-ui.card>

        <div class="sticky bottom-0 z-10 -mx-1 flex justify-end gap-2 border-t border-border bg-background/90 px-1 py-3 backdrop-blur">
            <x-ui.button type="submit" icon="check">Save landing page</x-ui.button>
        </div>
    </form>
@endsection
