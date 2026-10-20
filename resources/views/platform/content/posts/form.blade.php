@extends('layouts.platform')

@section('title', $post->exists ? 'Edit post' : 'New post')

@section('content')
    <x-ui.page-header :title="$post->exists ? $post->title : 'New post'" description="Leave the publish date empty to keep a draft. A future date schedules the post.">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Blog posts', 'href' => route('platform.content.posts.index')], ['label' => $post->exists ? $post->title : 'New post']]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @if ($post->exists && $post->isPublished())
                <x-ui.button variant="outline" icon="external-link" :href="route('blog.show', $post)" target="_blank" rel="noopener">View post</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="max-w-4xl space-y-6">
        <form method="POST" enctype="multipart/form-data"
            action="{{ $post->exists ? route('platform.content.posts.update', $post->id) : route('platform.content.posts.store') }}">
            @csrf
            @if ($post->exists)
                @method('PUT')
            @endif
            <x-ui.card title="Post">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Title" name="title" required class="sm:col-span-2">
                        <x-ui.input name="title" :value="$post->title" required maxlength="160" />
                    </x-ui.field>
                    <x-ui.field label="Address" name="slug" hint="Leave empty to build it from the title.">
                        <x-ui.input name="slug" :value="$post->slug" maxlength="180" placeholder="my-article" />
                    </x-ui.field>
                    <x-ui.field label="Publish date" name="published_at" hint="Empty keeps a draft.">
                        <x-ui.input type="datetime-local" name="published_at" :value="$post->published_at?->format('Y-m-d\TH:i')" />
                    </x-ui.field>
                    <x-ui.field label="Excerpt" name="excerpt" hint="One or two sentences for the article list." class="sm:col-span-2">
                        <x-ui.textarea name="excerpt" rows="2" maxlength="300" :value="$post->excerpt" />
                    </x-ui.field>
                    <x-ui.field label="Body" name="body" class="sm:col-span-2">
                        <x-ui.rich-text name="body" :value="$post->body" />
                    </x-ui.field>
                    <x-ui.field label="Search description" name="meta_description" hint="Optional. Defaults to the excerpt (about 150 characters)." class="sm:col-span-2">
                        <x-ui.textarea name="meta_description" rows="2" maxlength="300" :value="$post->meta_description" />
                    </x-ui.field>
                    <x-ui.field label="Cover image" name="cover" hint="Optional. JPG, PNG or WebP up to 2 MB; 1200×630 works best for sharing.">
                        <x-ui.input type="file" name="cover" accept="image/jpeg,image/png,image/webp" />
                    </x-ui.field>
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($post->cover_image)
                            <img src="{{ asset('storage/'.$post->cover_image) }}" alt="" class="h-16 w-auto rounded-lg object-cover">
                            <x-ui.checkbox name="remove_cover" label="Remove cover" />
                        @endif
                    </div>
                </div>
                <x-slot:footer class="justify-end">
                    <x-ui.button variant="outline" :href="route('platform.content.posts.index')">Back</x-ui.button>
                    <x-ui.button type="submit" icon="check">{{ $post->exists ? 'Save' : 'Add post' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        @if ($post->exists)
            <x-ui.card title="Delete post" description="Removes it from the blog. To hide it for now, clear the publish date instead.">
                <form method="POST" action="{{ route('platform.content.posts.destroy', $post->id) }}"
                    data-confirm="Delete this post?" data-confirm-button="Delete" data-confirm-text="“{{ $post->title }}” is removed. This can't be undone.">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="destructive" icon="trash-2">Delete post</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
@endsection
