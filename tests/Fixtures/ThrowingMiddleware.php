<?php

namespace Vinkius\Vurb\Tests\Fixtures;

use Closure;
use Vinkius\Vurb\Middleware\VurbMiddleware;

/**
 * Middleware that throws an exception — for testing pipeline resilience.
 */
class ThrowingMiddleware implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        throw new \RuntimeException('Middleware explosion');
    }
}
