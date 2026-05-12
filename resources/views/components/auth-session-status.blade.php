@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'auth-success-box']) }}>
        {{ $status }}
    </div>
@endif
