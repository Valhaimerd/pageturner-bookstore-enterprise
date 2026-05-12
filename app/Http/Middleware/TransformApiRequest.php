<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TransformApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson()) {
            $request->merge($this->transformKeys($request->all(), fn (string $key) => Str::snake($key)));
        }

        if ($request->query->count() > 0) {
            $request->query->replace($this->transformKeys($request->query->all(), fn (string $key) => Str::snake($key)));
        }

        return $next($request);
    }

    protected function transformKeys(array $payload, callable $transformer): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $transformedKey = is_string($key) ? $transformer($key) : $key;
            $result[$transformedKey] = is_array($value) ? $this->transformKeys($value, $transformer) : $value;
        }

        return $result;
    }
}
