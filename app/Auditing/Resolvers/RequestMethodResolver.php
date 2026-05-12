<?php

namespace App\Auditing\Resolvers;

use Illuminate\Support\Facades\Request;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

class RequestMethodResolver implements Resolver
{
    public static function resolve(Auditable $auditable = null): string
    {
        return $auditable->preloadedResolverData['method'] ?? Request::method();
    }
}
