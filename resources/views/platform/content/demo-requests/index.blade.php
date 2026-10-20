@extends('layouts.platform')

@section('title', 'Inbox')

@use('App\Models\DemoRequest')

@section('content')
    <x-ui.page-header title="Inbox" description="Demo requests from the homepage and messages from the Contact page. New ones are also emailed to the support address in Site settings.">
        <x-slot:actions>
            <x-ui.button :variant="$status === 'open' ? null : 'outline'" :href="route('platform.content.demo-requests.index', array_filter(['source' => $source]))">Open ({{ $openCount }})</x-ui.button>
            <x-ui.button :variant="$status === 'handled' ? null : 'outline'" :href="route('platform.content.demo-requests.index', array_filter(['status' => 'handled', 'source' => $source]))">Handled</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Filter by source">
        @foreach (['' => 'All'] + DemoRequest::SOURCES as $key => $label)
            <a href="{{ route('platform.content.demo-requests.index', array_filter(['status' => $status === 'handled' ? 'handled' : null, 'source' => $key])) }}"
                class="badge" data-variant="{{ (string) $source === (string) $key ? 'primary' : 'outline' }}" @if ((string) $source === (string) $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($demoRequests->isEmpty())
        <x-ui.empty-state icon="inbox" :title="$status === 'open' ? 'No open requests' : 'Nothing handled yet'"
            :description="$status === 'open' ? 'New demo requests and contact messages show up here.' : 'Messages you mark as handled move here.'" />
    @else
        <x-ui.card flush class="py-0!">
            <x-ui.table>
                <thead>
                    <tr><th>Contact</th><th>Company</th><th>Message</th><th>Received</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody>
                    @foreach ($demoRequests as $demoRequest)
                        <tr>
                            <td data-label="Contact" data-primary>
                                <div class="min-w-0">
                                    <span class="badge mb-1" data-variant="{{ $demoRequest->source === DemoRequest::SOURCE_CONTACT ? 'secondary' : 'outline' }}">{{ DemoRequest::SOURCES[$demoRequest->source] ?? $demoRequest->source }}</span>
                                    <p class="font-medium text-foreground">{{ $demoRequest->name }}</p>
                                    <a href="mailto:{{ $demoRequest->email }}" class="block truncate text-xs text-muted-foreground hover:underline">{{ $demoRequest->email }}</a>
                                    @if ($demoRequest->phone)
                                        <p class="text-xs text-muted-foreground">{{ $demoRequest->phone }}</p>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Company">{{ $demoRequest->company ?: '—' }}</td>
                            <td data-label="Message" class="max-w-md whitespace-normal text-muted-foreground"><p class="whitespace-pre-line">{{ $demoRequest->message ?: '—' }}</p></td>
                            <td data-label="Received" class="whitespace-nowrap">
                                {{ $demoRequest->created_at->format('M j, Y g:i A') }}
                                @if ($demoRequest->handled_at)
                                    <p class="text-xs text-muted-foreground">Handled {{ $demoRequest->handled_at->format('M j, Y') }}{{ $demoRequest->handler ? ' by '.$demoRequest->handler->name : '' }}</p>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @unless ($demoRequest->handled_at)
                                        <form method="POST" action="{{ route('platform.content.demo-requests.update', $demoRequest) }}">
                                            @csrf
                                            @method('PUT')
                                            <x-ui.button type="submit" variant="ghost" size="sm" icon="check">Handled</x-ui.button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('platform.content.demo-requests.destroy', $demoRequest) }}"
                                        data-confirm="Delete this request?" data-confirm-button="Delete" data-confirm-text="{{ $demoRequest->name }}'s request is removed. This can't be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="ghost" size="sm" icon="trash-2" class="text-destructive">Delete</x-ui.button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
        <div class="mt-4">{{ $demoRequests->links() }}</div>
    @endif
@endsection
