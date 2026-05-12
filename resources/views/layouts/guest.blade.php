<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600;700;800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-ink-800">
        <div class="guest-shell flex min-h-screen flex-col">
            <div class="flex flex-1 items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
                <div class="w-full max-w-md">
                    <div class="auth-logo-wrap">
                        <a href="/" class="auth-logo-badge">
                            <x-application-logo class="h-12 w-12 fill-current text-brand-700" />
                        </a>

                        <div>
                            <a href="/" class="brand-logo-text">
                                Page<span class="brand-highlight">Turner</span>
                            </a>
                            <p class="mt-2 text-sm text-ink-500">
                                Calm, modern book shopping experience
                            </p>
                        </div>
                    </div>

                    <div class="auth-card">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            @include('layouts.footer')
        </div>
    </body>
</html>
