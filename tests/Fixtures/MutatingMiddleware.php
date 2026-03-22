<?php

namespace Vinkius\Vurb\Tests\Fixtures;

use Closure;
use Vinkius\Vurb\Middleware\VurbMiddleware;

/**
 * Middleware that mutates the input context — for testing pipeline data flow.
 */
class MutatingMiddleware implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        $context['input']['_injected'] = 'middleware_was_here';

        return $next($context);
    }
}
