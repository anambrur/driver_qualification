@props(['tabs', 'name', 'selected' => false])
<div role="tabpanel" id="{{ $tabs }}-panel-{{ $name }}" aria-labelledby="{{ $tabs }}-tab-{{ $name }}" tabindex="-1"
    @unless ($selected) hidden @endunless {{ $attributes }}>
    {{ $slot }}
</div>
