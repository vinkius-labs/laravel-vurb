<?php

namespace Vinkius\Vurb\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateVurbToken
{
    /**
     * Handle an incoming request — validates the internal Vurb token (timing-safe).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->header('X-Vurb-Token');
        $expected = config('vurb.internal_token');

        if (empty($expected)) {
            abort(500, 'VURB_INTERNAL_TOKEN is not configured.');
        }

        if (! hash_equals($expected, $provided ?? '')) {
            abort(403, 'Invalid Vurb token.');
        }

        return $next($request);
    }
}
