@extends('layouts.platform')

@section('title', 'Testimonials')

@section('content')
    <x-ui.page-header title="Testimonials" description="Customer quotes on the homepage. Active ones show in sort order.">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('platform.content.testimonials.create')">New testimonial</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($testimonials->isEmpty())
        <x-ui.empty-state icon="message-square-quote" title="No testimonials yet" description="The homepage hides the testimonials section until one is active.">
            <x-ui.button icon="plus" size="sm" :href="route('platform.content.testimonials.create')">New testimonial</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.card flush class="py-0!">
            <x-ui.table>
                <thead>
                    <tr><th>Customer</th><th>Quote</th><th>Rating</th><th>Order</th><th>Active</th></tr>
                </thead>
                <tbody>
                    @foreach ($testimonials as $testimonial)
                        <tr>
                            <td data-label="Customer" data-primary>
                                @include('platform.partials.cell', [
                                    'title' => $testimonial->name,
                                    'href' => route('platform.content.testimonials.edit', $testimonial),
                                    'sub' => collect([$testimonial->role, $testimonial->company])->filter()->join(', '),
                                ])
                            </td>
                            <td data-label="Quote" class="max-w-md text-muted-foreground"><p class="line-clamp-2">{{ $testimonial->quote }}</p></td>
                            <td data-label="Rating" class="tabular-nums">{{ $testimonial->rating }} / 5</td>
                            <td data-label="Order" class="tabular-nums">{{ $testimonial->sort_order }}</td>
                            <td data-label="Active"><span class="badge" data-variant="{{ $testimonial->is_active ? 'secondary' : 'outline' }}">{{ $testimonial->is_active ? 'Yes' : 'No' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
@endsection
