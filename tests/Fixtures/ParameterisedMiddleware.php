<?php

namespace Vinkius\Vurb\Tests\Fixtures;

use Closure;
use Vinkius\Vurb\Middleware\VurbMiddleware;

/**
 * Middleware that accepts parameters — for testing middleware:param syntax.
 */
class ParameterisedMiddleware implements VurbMiddleware
{
    public function handle(array $context, Closure $next, string $requiredRole = 'user'): mixed
    {
        $context['input']['_middleware_role'] = $requiredRole;

        return $next($context);
    }
}
