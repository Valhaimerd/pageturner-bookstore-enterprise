<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary-ui']) }}>
    {{ $slot }}
</button>
