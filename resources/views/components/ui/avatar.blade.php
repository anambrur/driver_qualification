@props(['src' => null, 'name' => '', 'size' => null])
@include('partials.ui.avatar', ['src' => $src, 'name' => $name, 'size' => $size, 'class' => $attributes->get('class')])
