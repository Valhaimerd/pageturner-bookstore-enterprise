@props(['active'])

@php
$classes = ($active ?? false)
    ? 'mobile-nav-link-base mobile-nav-link-active'
    : 'mobile-nav-link-base mobile-nav-link-inactive';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
