@extends('layouts.platform')

@section('title', 'Edit '.$page->title)

@section('content')
    <x-ui.page-header :title="$page->title" :description="'Public address: '.$page->url()">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Pages', 'href' => route('platform.content.pages.index')], ['label' => $page->title]]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @if ($page->is_published)
                <x-ui.button variant="outline" icon="external-link" :href="$page->url()" target="_blank" rel="noopener">View page</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($page->isUnreviewedTemplate())
        <x-ui.alert tone="warning" class="mb-6 max-w-4xl">This is a seeded legal template. Have it reviewed for your business, then save it.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('platform.content.pages.update', $page) }}" class="max-w-4xl">
        @csrf
        @method('PUT')
        <x-ui.card title="Content">
            <div class="grid gap-4">
                <x-ui.field label="Title" name="title" required hint="The page heading, browser tab and search result title.">
                    <x-ui.input name="title" :value="$page->title" required maxlength="120" />
                </x-ui.field>
                <x-ui.field label="Summary" name="summary" hint="Shown under the heading and used as the search result description (about 150 characters).">
                    <x-ui.textarea name="summary" rows="2" maxlength="300" :value="$page->summary" />
                </x-ui.field>
                <x-ui.field label="{{ $page->slug === 'blog' ? 'Intro (above the articles)' : 'Body' }}" name="body">
                    <x-ui.rich-text name="body" :value="$page->body" />
                </x-ui.field>
                <x-ui.switch name="is_published" label="Published" description="Hidden pages return 404 and their footer links disappear." :checked="(bool) $page->is_published" />
            </div>
            <x-slot:footer class="justify-end">
                <x-ui.button variant="outline" :href="route('platform.content.pages.index')">Back</x-ui.button>
                <x-ui.button type="submit" icon="check">Save page</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection
