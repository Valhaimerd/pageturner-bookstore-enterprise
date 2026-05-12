<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-danger-ui']) }}>
    {{ $slot }}
</button>
