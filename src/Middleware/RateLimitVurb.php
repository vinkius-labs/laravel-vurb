<?php

namespace Vinkius\Vurb\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitVurb implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        $key = 'vurb:' . $context['tool'] . ':' . ($context['user']?->getAuthIdentifier() ?? 'anonymous');

        $maxAttempts = 60; // per minute
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return [
                'error' => true,
                'code' => 'RATE_LIMITED',
                'message' => "Too many requests for tool '{$context['tool']}'.",
                'retryAfter' => $retryAfter,
            ];
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($context);
    }
}
