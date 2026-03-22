<?php

namespace Vinkius\Vurb\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;

class RequirePermission implements VurbMiddleware
{
    public function handle(array $context, Closure $next, string ...$permissions): mixed
    {
        $user = $context['user'];

        if ($user === null) {
            return [
                'error' => true,
                'code' => 'UNAUTHORIZED',
                'message' => 'Authentication required.',
            ];
        }

        foreach ($permissions as $permission) {
            if (! Gate::forUser($user)->allows($permission)) {
                return [
                    'error' => true,
                    'code' => 'FORBIDDEN',
                    'message' => "Missing permission: {$permission}",
                ];
            }
        }

        return $next($context);
    }
}
