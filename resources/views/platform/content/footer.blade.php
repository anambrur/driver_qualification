@extends('layouts.platform')

@section('title', 'Footer')

@use('App\Support\Footer')

@php
    $list = fn (string $key) => old($key) ?? data_get($footer, $key, []);
    $targets = Footer::targets();
    $networks = collect(Footer::NETWORKS)->map(fn ($network) => $network[0])->all();
    $badgeIcons = collect(Footer::BADGE_ICONS)->mapWithKeys(fn ($icon) => [$icon => \Illuminate\Support\Str::headline($icon)])->all();
    $columnErrors = collect($errors->get('columns.*'))->flatten()->merge($errors->get('columns'))->unique();
@endphp

@section('content')
    <x-ui.page-header title="Footer" description="The footer on every public page. Links point to site pages or homepage sections; a link to a hidden page is left out automatically.">
        <x-slot:actions>
            <x-ui.button variant="outline" icon="file-text" :href="route('platform.content.pages.index')">Edit pages</x-ui.button>
            <x-ui.button variant="outline" icon="external-link" :href="route('home')" target="_blank" rel="noopener">View site</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('platform.content.footer.update') }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Brand and contact" description="Next to the logo. The logo itself comes from Site settings.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Tagline" name="brand[tagline]" class="sm:col-span-2">
                    <x-ui.textarea name="brand[tagline]" rows="2" maxlength="300" :value="$footer['brand']['tagline']" />
                </x-ui.field>
                <x-ui.field label="Email" name="contact[email]">
                    <x-ui.input type="email" name="contact[email]" :value="$footer['contact']['email']" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Phone" name="contact[phone]">
                    <x-ui.input name="contact[phone]" :value="$footer['contact']['phone']" maxlength="50" />
                </x-ui.field>
                <x-ui.field label="Address" name="contact[address]" class="sm:col-span-2" hint="Also shown on the Contact page.">
                    <x-ui.textarea name="contact[address]" rows="2" maxlength="300" :value="$footer['contact']['address']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Social links" description="Icons under the contact details. Profile URLs must start with https://.">
            @include('platform.content.partials.rows', [
                'name' => 'social', 'rows' => $list('social'), 'max' => Footer::LIMITS['social'],
                'label' => 'Profile', 'empty' => 'No social links.',
                'blank' => ['network' => 'facebook', 'url' => ''],
                'fields' => [
                    'network' => ['label' => 'Network', 'type' => 'select', 'options' => $networks],
                    'url' => ['label' => 'Profile URL', 'max' => 255],
                ],
            ])
        </x-ui.card>

        <x-ui.card title="Link columns" description="Up to {{ Footer::LIMITS['columns'] }} columns. Each link opens a site page or a homepage section; a column with no visible links is hidden.">
            <div x-data="{
                    columns: @js(array_values($list('columns'))),
                    maxColumns: {{ Footer::LIMITS['columns'] }},
                    maxLinks: {{ Footer::LIMITS['links'] }},
                    blankLink: @js(['label' => '', 'target' => array_key_first($targets)]),
                }" class="grid gap-4">
                <template x-for="(column, c) in columns" :key="c">
                    <div class="grid gap-3 rounded-lg border border-border p-4">
                        <div class="flex items-end gap-3">
                            <div class="field flex-1 gap-2">
                                <label class="label" x-bind:for="`columns-${c}-heading`">Column heading</label>
                                <input type="text" class="input w-full" maxlength="40" x-bind:id="`columns-${c}-heading`" x-bind:name="`columns[${c}][heading]`" x-model="column.heading">
                            </div>
                            <x-ui.button variant="ghost" size="sm" icon="trash-2" class="text-destructive" x-on:click="columns.splice(c, 1)">Remove column</x-ui.button>
                        </div>

                        <template x-for="(link, l) in column.links" :key="l">
                            <div class="grid items-end gap-3 rounded-md bg-muted/40 p-3 sm:grid-cols-[1fr_1fr_auto]">
                                <div class="field gap-2">
                                    <label class="label" x-bind:for="`columns-${c}-links-${l}-label`">Label</label>
                                    <input type="text" class="input w-full" maxlength="40" x-bind:id="`columns-${c}-links-${l}-label`" x-bind:name="`columns[${c}][links][${l}][label]`" x-model="link.label">
                                </div>
                                <div class="field gap-2">
                                    <label class="label" x-bind:for="`columns-${c}-links-${l}-target`">Opens</label>
                                    <select class="select w-full" x-bind:id="`columns-${c}-links-${l}-target`" x-bind:name="`columns[${c}][links][${l}][target]`" x-model="link.target">
                                        @foreach ($targets as $value => $text)
                                            <option value="{{ $value }}">{{ $text }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex gap-1">
                                    @foreach ($pages as $page)
                                        <x-ui.button variant="ghost" size="sm" icon="pencil" x-cloak x-show="link.target === 'page:{{ $page->slug }}'"
                                            :href="route('platform.content.pages.edit', $page)" title="Edit {{ $page->title }}">
                                            Edit page
                                        </x-ui.button>
                                    @endforeach
                                    <x-ui.button variant="ghost" size="sm" icon="x" class="text-destructive" x-on:click="column.links.splice(l, 1)">
                                        <span class="sr-only">Remove link</span>
                                    </x-ui.button>
                                </div>
                            </div>
                        </template>

                        <div class="flex items-center gap-3">
                            <x-ui.button variant="outline" size="sm" icon="plus" x-on:click="column.links.push({ ...blankLink })" x-bind:disabled="column.links.length >= maxLinks">Add link</x-ui.button>
                            <span class="text-xs text-muted-foreground" x-text="`${column.links.length} of ${maxLinks}`"></span>
                        </div>
                    </div>
                </template>
                <p x-show="columns.length === 0" class="text-sm text-muted-foreground">No link columns.</p>
                @if ($columnErrors->isNotEmpty())
                    <div role="alert" class="text-sm text-destructive">
                        @foreach ($columnErrors as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                @endif
                <div class="flex items-center gap-3">
                    <x-ui.button variant="outline" size="sm" icon="plus" x-on:click="columns.push({ heading: '', links: [] })" x-bind:disabled="columns.length >= maxColumns">Add column</x-ui.button>
                    <span class="text-xs text-muted-foreground" x-text="`${columns.length} of ${maxColumns}`"></span>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Pages" description="Hidden pages drop out of the footer. Open a page to change its text.">
            <ul class="divide-y divide-border">
                @foreach ($pages as $page)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="flex items-center gap-2 text-sm font-medium">
                            {{ $page->title }}
                            <span class="badge" data-variant="{{ $page->is_published ? 'secondary' : 'outline' }}">{{ $page->is_published ? 'Published' : 'Hidden' }}</span>
                        </span>
                        <x-ui.button variant="ghost" size="sm" icon="pencil" :href="route('platform.content.pages.edit', $page)">Edit</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <x-ui.card title="Trust badges" description="Short, true statements under the links. Only claim what you can prove.">
            @include('platform.content.partials.rows', [
                'name' => 'badges', 'rows' => $list('badges'), 'max' => Footer::LIMITS['badges'],
                'label' => 'Badge', 'empty' => 'No badges.',
                'blank' => ['icon' => Footer::BADGE_ICONS[0], 'label' => ''],
                'fields' => [
                    'icon' => ['label' => 'Icon', 'type' => 'select', 'options' => $badgeIcons],
                    'label' => ['label' => 'Text', 'max' => 60],
                ],
            ])
        </x-ui.card>

        <x-ui.card title="Sign-up band" description="A call to action above the footer on inner pages. The homepage has its own. The button opens registration.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.switch name="cta[enabled]" label="Show the band" :checked="(bool) $footer['cta']['enabled']" class="sm:col-span-2" />
                <x-ui.field label="Title" name="cta[title]" required>
                    <x-ui.input name="cta[title]" :value="$footer['cta']['title']" required maxlength="120" />
                </x-ui.field>
                <x-ui.field label="Button label" name="cta[button_label]" required>
                    <x-ui.input name="cta[button_label]" :value="$footer['cta']['button_label']" required maxlength="40" />
                </x-ui.field>
                <x-ui.field label="Text" name="cta[text]" class="sm:col-span-2">
                    <x-ui.textarea name="cta[text]" rows="2" maxlength="300" :value="$footer['cta']['text']" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Bottom bar">
            <div class="grid gap-4">
                <x-ui.field label="Copyright line" name="bottom[copyright]" required hint="{year} becomes the current year and {company} the legal name from Site settings.">
                    <x-ui.input name="bottom[copyright]" :value="$footer['bottom']['copyright']" required maxlength="200" />
                </x-ui.field>
                <x-ui.switch name="bottom[back_to_top]" label="Show the Back to top button" :checked="(bool) $footer['bottom']['back_to_top']" />
            </div>
        </x-ui.card>

        <div class="sticky bottom-0 z-10 -mx-1 flex justify-end gap-2 border-t border-border bg-background/90 px-1 py-3 backdrop-blur">
            <x-ui.button type="submit" icon="check">Save footer</x-ui.button>
        </div>
    </form>
@endsection
