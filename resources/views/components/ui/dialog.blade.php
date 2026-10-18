{{--
    Native <dialog>. Open with: document.getElementById('{id}').showModal()
    size: sm (28rem) | md (32rem, default) | lg (42rem) | xl (56rem) | 2xl (72rem). The body scrolls
    between a fixed header and footer; below 640px it opens as a bottom sheet.
--}}
@props(['id', 'title' => null, 'description' => null, 'icon' => null, 'size' => 'md', 'closeOnOverlay' => true])
<dialog id="{{ $id }}"
    @if ($title) aria-labelledby="{{ $id }}-title" @endif
    @if ($description) aria-describedby="{{ $id }}-description" @endif
    @if ($closeOnOverlay) onclick="if (event.target === this) this.close()" @endif
    {{ $attributes->class('dialog')->merge(['data-size' => $size]) }}>
    <div>
        @if ($title || $description)
            <header>
                @if ($icon)
                    <span class="dialog-icon" aria-hidden="true">{{ svg('lucide-'.$icon, 'size-5') }}</span>
                @endif
                <div class="min-w-0">
                    @if ($title)
                        <h2 id="{{ $id }}-title">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p id="{{ $id }}-description">{{ $description }}</p>
                    @endif
                </div>
            </header>
        @endif
        <section class="custom-scrollbar">{{ $slot }}</section>
        @isset($footer)
            <footer {{ $footer->attributes }}>{{ $footer }}</footer>
        @endisset
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="Close dialog" onclick="this.closest('dialog').close()">
            {{ svg('lucide-x', '', ['aria-hidden' => 'true']) }}
        </button>
    </div>
</dialog>
