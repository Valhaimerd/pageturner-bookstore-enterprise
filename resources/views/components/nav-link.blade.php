@props(['active'])

@php
$classes = ($active ?? false)
    ? 'nav-link-base nav-link-active'
    : 'nav-link-base nav-link-inactive';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
