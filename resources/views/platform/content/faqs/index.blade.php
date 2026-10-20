@extends('layouts.platform')

@section('title', 'FAQs')

@section('content')
    <x-ui.page-header title="FAQs" description="Questions and answers on the homepage. Active ones show in sort order.">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('platform.content.faqs.create')">New question</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($faqs->isEmpty())
        <x-ui.empty-state icon="circle-help" title="No questions yet" description="The homepage hides the FAQ section until one is active.">
            <x-ui.button icon="plus" size="sm" :href="route('platform.content.faqs.create')">New question</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.card flush class="py-0!">
            <x-ui.table>
                <thead>
                    <tr><th>Question</th><th>Order</th><th>Active</th></tr>
                </thead>
                <tbody>
                    @foreach ($faqs as $faq)
                        <tr>
                            <td data-label="Question" data-primary>
                                @include('platform.partials.cell', ['title' => $faq->question, 'href' => route('platform.content.faqs.edit', $faq), 'sub' => \Illuminate\Support\Str::limit($faq->answer, 90)])
                            </td>
                            <td data-label="Order" class="tabular-nums">{{ $faq->sort_order }}</td>
                            <td data-label="Active"><span class="badge" data-variant="{{ $faq->is_active ? 'secondary' : 'outline' }}">{{ $faq->is_active ? 'Yes' : 'No' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
@endsection
