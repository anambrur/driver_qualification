@extends('layouts.platform')

@section('title', 'Pages')

@section('content')
    <x-ui.page-header title="Pages" description="The public site pages linked from the footer. Unpublished pages return 404 and drop out of the footer.">
        <x-slot:actions>
            <x-ui.button variant="outline" icon="panel-bottom" :href="route('platform.content.footer.edit')">Footer links</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush class="py-0!">
        <x-ui.table>
            <thead>
                <tr><th>Page</th><th>Address</th><th>Status</th><th>Updated</th></tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr>
                        <td data-label="Page" data-primary>
                            @include('platform.partials.cell', [
                                'title' => $page->title,
                                'href' => route('platform.content.pages.edit', $page),
                                'sub' => $page->summary ? \Illuminate\Support\Str::limit($page->summary, 70) : null,
                            ])
                        </td>
                        <td data-label="Address" class="text-muted-foreground">/{{ ltrim(parse_url($page->url(), PHP_URL_PATH), '/') }}</td>
                        <td data-label="Status">
                            <span class="badge" data-variant="{{ $page->is_published ? 'secondary' : 'outline' }}">{{ $page->is_published ? 'Published' : 'Hidden' }}</span>
                            @if ($page->isUnreviewedTemplate())
                                <span class="badge" data-variant="destructive" title="Seeded legal template. Have it reviewed, then save the page.">Template, review before launch</span>
                            @endif
                        </td>
                        <td data-label="Updated" class="text-muted-foreground">{{ $page->updated_at?->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection
