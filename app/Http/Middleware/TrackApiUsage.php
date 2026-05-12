<?php

namespace App\Http\Middleware;

use App\Models\ApiRateLimitHit;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackApiUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (HttpResponseException $exception) {
            $this->recordHit($request, $exception->getResponse());

            throw $exception;
        }

        $this->recordHit($request, $response);

        return $response;
    }

    protected function recordHit(Request $request, Response $response): void
    {
        ApiRateLimitHit::create([
            'user_id' => $request->user()?->id,
            'tier' => $this->resolveTier($request),
            'endpoint' => $request->route()?->getName() ?: $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'limit' => $response->headers->get('X-RateLimit-Limit'),
            'remaining' => $response->headers->get('X-RateLimit-Remaining'),
            'retry_after' => $response->headers->get('Retry-After'),
            'status_code' => $response->getStatusCode(),
            'throttled' => $response->getStatusCode() === 429,
            'metadata' => [
                'route' => $request->route()?->uri(),
            ],
            'created_at' => now(),
        ]);
    }

    protected function resolveTier(Request $request): string
    {
        if (str_contains((string) $request->route()?->getName(), 'auth.')) {
            return 'auth';
        }

        if (! $request->user()) {
            return 'public';
        }

        if ($request->user()->isAdmin()) {
            return 'admin';
        }

        return $request->user()->isPremium() ? 'premium' : 'standard';
    }
}
