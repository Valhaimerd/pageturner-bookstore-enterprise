<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-grid">
            <div class="site-footer-brand">
                <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-white shadow-soft ring-1 ring-sage-100">
                    <x-application-logo class="h-5 w-5 fill-current text-brand-700" />
                </span>

                <div>
                    <p class="site-footer-title">
                        Page<span class="brand-highlight">Turner</span>
                    </p>
                    <p class="site-footer-text">Modern bookstore experience</p>
                </div>
            </div>

            <div>
                <p class="site-footer-heading">Quick Links</p>
                <div class="site-footer-links mt-1">
                    <a href="{{ route('home') }}" class="site-footer-link">Home</a>
                    <a href="{{ route('books.index') }}" class="site-footer-link">Books</a>

                    @guest
                        <a href="{{ route('login') }}" class="site-footer-link">Login</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="site-footer-link">Register</a>
                        @endif
                    @endguest

                    @auth
                        <a href="{{ route('profile.edit') }}" class="site-footer-link">Profile</a>
                    @endauth
                </div>
            </div>
        </div>

        <div class="site-footer-bottom">
            <p>© {{ now()->year }} PageTurner</p>
            <p>Teal + sage UI</p>
        </div>
    </div>
</footer>
