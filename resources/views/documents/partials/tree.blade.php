{{-- One level of the folder tree. Needs $browser, $parentKey (0 = roots). --}}
@php($level = $browser['children'][$parentKey] ?? collect())
@if ($level->isNotEmpty())
    <ul @class(['space-y-0.5', 'ml-4 border-l border-border pl-2' => $parentKey !== 0])>
        @foreach ($level as $folder)
            @php($open = $browser['current']?->is($folder))
            <li>
                <a href="{{ \App\Services\Documents\FolderBrowser::url($folder) }}" @if ($open) aria-current="page" @endif
                    @class(['flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent', 'bg-accent font-medium text-foreground' => $open, 'text-muted-foreground' => ! $open])>
                    {{ svg($folder->isSystem() ? 'lucide-folder-lock' : ($open ? 'lucide-folder-open' : 'lucide-folder'), 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                    <span class="truncate">{{ $folder->label() }}</span>
                </a>
                @include('documents.partials.tree', ['parentKey' => $folder->id])
            </li>
        @endforeach
    </ul>
@endif
