@extends('layouts.platform')

@section('title', $testimonial->exists ? 'Edit testimonial' : 'New testimonial')

@section('content')
    <x-ui.page-header :title="$testimonial->exists ? $testimonial->name : 'New testimonial'" description="Shown on the homepage while active.">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Testimonials', 'href' => route('platform.content.testimonials.index')], ['label' => $testimonial->exists ? $testimonial->name : 'New testimonial']]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <div class="max-w-3xl space-y-6">
        <form method="POST" enctype="multipart/form-data"
            action="{{ $testimonial->exists ? route('platform.content.testimonials.update', $testimonial) : route('platform.content.testimonials.store') }}">
            @csrf
            @if ($testimonial->exists)
                @method('PUT')
            @endif
            <x-ui.card title="Testimonial">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Name" name="name" required>
                        <x-ui.input name="name" :value="$testimonial->name" required maxlength="100" />
                    </x-ui.field>
                    <x-ui.field label="Role" name="role" hint="For example Fleet Manager.">
                        <x-ui.input name="role" :value="$testimonial->role" maxlength="100" />
                    </x-ui.field>
                    <x-ui.field label="Company" name="company" hint="For example JM Logistics, Texas.">
                        <x-ui.input name="company" :value="$testimonial->company" maxlength="150" />
                    </x-ui.field>
                    <x-ui.field label="Rating" name="rating" required>
                        <x-ui.select name="rating" :value="$testimonial->rating" :options="[5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star']" required />
                    </x-ui.field>
                    <x-ui.field label="Quote" name="quote" required class="sm:col-span-2">
                        <x-ui.textarea name="quote" rows="4" maxlength="1000" required :value="$testimonial->quote" />
                    </x-ui.field>
                    <x-ui.field label="Photo" name="avatar" hint="Optional. JPG, PNG or WebP, up to 1 MB. Without a photo the first letter of the name shows.">
                        <x-ui.input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" />
                    </x-ui.field>
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($testimonial->avatar)
                            <img src="{{ asset('storage/'.$testimonial->avatar) }}" alt="" class="size-12 rounded-full object-cover">
                            <x-ui.checkbox name="remove_avatar" label="Remove photo" />
                        @endif
                    </div>
                    <x-ui.field label="Sort order" name="sort_order" hint="Lower numbers show first.">
                        <x-ui.input type="number" min="0" name="sort_order" :value="$testimonial->sort_order ?? 0" />
                    </x-ui.field>
                    <div class="flex items-end pb-2">
                        <x-ui.switch name="is_active" label="Active" :checked="(bool) $testimonial->is_active" />
                    </div>
                </div>
                <x-slot:footer class="justify-end">
                    <x-ui.button variant="outline" :href="route('platform.content.testimonials.index')">Back</x-ui.button>
                    <x-ui.button type="submit" icon="check">{{ $testimonial->exists ? 'Save' : 'Add testimonial' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        @if ($testimonial->exists)
            <x-ui.card title="Delete testimonial" description="Removes it from the homepage. To hide it for now, switch off Active instead.">
                <form method="POST" action="{{ route('platform.content.testimonials.destroy', $testimonial) }}"
                    data-confirm="Delete this testimonial?" data-confirm-button="Delete" data-confirm-text="{{ $testimonial->name }}'s quote is removed. This can't be undone.">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="destructive" icon="trash-2">Delete testimonial</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
@endsection
