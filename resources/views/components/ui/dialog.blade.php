{{-- Native <dialog>. Open with: document.getElementById('{id}').showModal() --}}
@props(['id', 'title' => null, 'description' => null, 'closeOnOverlay' => true])
<dialog id="{{ $id }}"
    @if ($title) aria-labelledby="{{ $id }}-title" @endif
    @if ($description) aria-describedby="{{ $id }}-description" @endif
    @if ($closeOnOverlay) onclick="if (event.target === this) this.close()" @endif
    {{ $attributes->class('dialog') }}>
    <div>
        @if ($title || $description)
            <header>
                @if ($title)
                    <h2 id="{{ $id }}-title">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p id="{{ $id }}-description">{{ $description }}</p>
                @endif
            </header>
        @endif
        <section>{{ $slot }}</section>
        @isset($footer)
            <footer {{ $footer->attributes }}>{{ $footer }}</footer>
        @endisset
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="Close dialog" onclick="this.closest('dialog').close()">
            {{ svg('lucide-x', '', ['aria-hidden' => 'true']) }}
        </button>
    </div>
</dialog>
