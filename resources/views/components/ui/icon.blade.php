@props(['name'])
{{ svg('lucide-'.$name, $attributes->get('class', ''), array_merge(['aria-hidden' => 'true', 'focusable' => 'false'], $attributes->except('class')->getAttributes())) }}
