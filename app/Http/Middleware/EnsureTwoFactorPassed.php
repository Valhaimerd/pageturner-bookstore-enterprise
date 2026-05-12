<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorPassed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->two_factor_enabled) {
            return $next($request);
        }

        if ($request->session()->get('two_factor_passed') === true) {
            return $next($request);
        }

        if (
            $request->routeIs('twofactor.*') ||
            $request->routeIs('logout') ||
            $request->routeIs('verification.*')
        ) {
            return $next($request);
        }

        return redirect()->route('twofactor.challenge');
    }
}
