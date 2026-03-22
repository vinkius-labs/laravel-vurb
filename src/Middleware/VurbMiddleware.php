<?php

namespace Vinkius\Vurb\Middleware;

use Closure;

/**
 * Interface for Vurb tool-level middleware.
 *
 * Context array contains:
 * - 'tool': string — tool name
 * - 'input': array — tool input arguments
 * - 'request': Request — the HTTP request
 * - 'user': ?Authenticatable — current user
 */
interface VurbMiddleware
{
    /**
     * Handle the tool execution context.
     *
     * @param array $context Tool execution context
     * @param Closure $next Next middleware in the pipeline
     * @return mixed
     */
    public function handle(array $context, Closure $next): mixed;
}
