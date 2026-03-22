<?php

namespace Vinkius\Vurb\Tests\Fixtures;

use Closure;
use Vinkius\Vurb\Middleware\VurbMiddleware;

/**
 * Middleware that short-circuits the pipeline returning an error array.
 */
class BlockingMiddleware implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        return [
            'error' => true,
            'code' => 'BLOCKED',
            'message' => 'Request blocked by middleware.',
        ];
    }
}
