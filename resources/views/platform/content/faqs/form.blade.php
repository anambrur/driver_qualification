@extends('layouts.platform')

@section('title', $faq->exists ? 'Edit question' : 'New question')

@section('content')
    <x-ui.page-header :title="$faq->exists ? 'Edit question' : 'New question'" description="Shown in the homepage FAQ while active.">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'FAQs', 'href' => route('platform.content.faqs.index')], ['label' => $faq->exists ? 'Edit question' : 'New question']]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <div class="max-w-3xl space-y-6">
        <form method="POST" action="{{ $faq->exists ? route('platform.content.faqs.update', $faq) : route('platform.content.faqs.store') }}">
            @csrf
            @if ($faq->exists)
                @method('PUT')
            @endif
            <x-ui.card title="Question">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Question" name="question" required class="sm:col-span-2">
                        <x-ui.input name="question" :value="$faq->question" required maxlength="255" />
                    </x-ui.field>
                    <x-ui.field label="Answer" name="answer" required class="sm:col-span-2">
                        <x-ui.textarea name="answer" rows="6" maxlength="3000" required :value="$faq->answer" />
                    </x-ui.field>
                    <x-ui.field label="Sort order" name="sort_order" hint="Lower numbers show first.">
                        <x-ui.input type="number" min="0" name="sort_order" :value="$faq->sort_order ?? 0" />
                    </x-ui.field>
                    <div class="flex items-end pb-2">
                        <x-ui.switch name="is_active" label="Active" :checked="(bool) $faq->is_active" />
                    </div>
                </div>
                <x-slot:footer class="justify-end">
                    <x-ui.button variant="outline" :href="route('platform.content.faqs.index')">Back</x-ui.button>
                    <x-ui.button type="submit" icon="check">{{ $faq->exists ? 'Save' : 'Add question' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        @if ($faq->exists)
            <x-ui.card title="Delete question" description="Removes it from the homepage. To hide it for now, switch off Active instead.">
                <form method="POST" action="{{ route('platform.content.faqs.destroy', $faq) }}"
                    data-confirm="Delete this question?" data-confirm-button="Delete" data-confirm-text="It is removed from the homepage FAQ. This can't be undone.">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="destructive" icon="trash-2">Delete question</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
@endsection
