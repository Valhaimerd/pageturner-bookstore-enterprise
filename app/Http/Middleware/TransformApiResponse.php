<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TransformApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        $data = $this->camelizeKeys($data);
        $data = $this->applyFieldFiltering($data, $request);

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $etag = '"'.sha1((string) $encoded).'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->noContent(304, ['ETag' => $etag]);
        }

        $response->setData($data);
        $response->headers->set('ETag', $etag);

        return $response;
    }

    protected function camelizeKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $transformed = [];

        foreach ($value as $key => $item) {
            $transformedKey = is_string($key) ? Str::camel($key) : $key;
            $transformed[$transformedKey] = is_array($item) ? $this->camelizeKeys($item) : $item;
        }

        return $transformed;
    }

    protected function applyFieldFiltering(array $payload, Request $request): array
    {
        $fields = collect(explode(',', (string) $request->query('fields')))
            ->map(fn (string $field) => trim(Str::camel($field)))
            ->filter()
            ->values()
            ->all();

        if ($fields === []) {
            return $payload;
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data'] = array_map(fn ($item) => is_array($item) ? array_intersect_key($item, array_flip($fields)) : $item, $payload['data']);

            return $payload;
        }

        return array_intersect_key($payload, array_flip($fields));
    }
}
