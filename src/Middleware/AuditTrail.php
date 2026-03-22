<?php

namespace Vinkius\Vurb\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class AuditTrail implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        $startTime = hrtime(true);

        $result = $next($context);

        $latencyMs = (hrtime(true) - $startTime) / 1e6;

        Log::channel(config('logging.default'))->info('Vurb tool executed', [
            'tool' => $context['tool'],
            'input' => $context['input'],
            'user_id' => $context['user']?->getAuthIdentifier(),
            'latency_ms' => round($latencyMs, 2),
            'is_error' => is_array($result) && ($result['error'] ?? false),
        ]);

        return $result;
    }
}
