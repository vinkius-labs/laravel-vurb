<?php

/**
 * MIDDLEWARE & AUTH EXAMPLE — Multi-Tenant Application
 *
 * Demonstrates VurbMiddleware patterns:
 *   - Custom middleware (VurbMiddleware interface)
 *   - Audit trail logging
 *   - RBAC with RequirePermission
 *   - Per-router middleware
 *   - Context array: tool, input, request, user
 */

// ═══════════════════════════════════════════════════════════════
// CUSTOM MIDDLEWARE: RequireTenantAccess
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Middleware;

use Closure;
use Vinkius\Vurb\Middleware\VurbMiddleware;

/**
 * Middleware that validates the user belongs to the requested tenant.
 * Returns a structured error if access is denied.
 */
class RequireTenantAccess implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        $user = $context['user'];
        $tenantId = $context['input']['tenant_id'] ?? null;

        if ($user === null) {
            return [
                'error'   => true,
                'code'    => 'UNAUTHORIZED',
                'message' => 'Authentication required.',
            ];
        }

        if ($tenantId && ! $user->belongsToTenant($tenantId)) {
            return [
                'error'   => true,
                'code'    => 'FORBIDDEN',
                'message' => "Access denied for tenant {$tenantId}.",
            ];
        }

        return $next($context);
    }
}


// ═══════════════════════════════════════════════════════════════
// CUSTOM MIDDLEWARE: LogToolExecution (Audit Trail)
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Vinkius\Vurb\Middleware\VurbMiddleware;

class LogToolExecution implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        $startTime = hrtime(true);

        $result = $next($context);

        $latencyMs = (hrtime(true) - $startTime) / 1e6;
        $isError = is_array($result) && ($result['error'] ?? false);

        Log::channel('vurb')->info('Tool executed', [
            'tool'       => $context['tool'],
            'user_id'    => $context['user']?->getAuthIdentifier(),
            'latency_ms' => round($latencyMs, 2),
            'is_error'   => $isError,
            'ip'         => $context['request']?->ip(),
        ]);

        return $result;
    }
}


// ═══════════════════════════════════════════════════════════════
// CUSTOM MIDDLEWARE: ValidateJsonInput
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Middleware;

use Closure;
use Vinkius\Vurb\Middleware\VurbMiddleware;

class ValidateJsonInput implements VurbMiddleware
{
    public function handle(array $context, Closure $next): mixed
    {
        // Sanitize inputs — strip HTML tags from string values
        $sanitized = array_map(function ($value) {
            return is_string($value) ? strip_tags($value) : $value;
        }, $context['input']);

        $context['input'] = $sanitized;

        return $next($context);
    }
}


// ═══════════════════════════════════════════════════════════════
// ROUTER: Admin Tools with Shared Middleware
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Tools\Admin;

use Vinkius\Vurb\Tools\VurbRouter;

/**
 * All tools in app/Vurb/Tools/Admin/ inherit:
 *   - The 'admin' prefix (admin.list_users, admin.ban_user, etc.)
 *   - RequireTenantAccess + LogToolExecution middleware
 *   - RequirePermission middleware for 'admin.access'
 */
class Router extends VurbRouter
{
    public string $prefix = 'admin';
    public string $description = 'Administrative operations — user management, billing overrides';
    public array $middleware = [
        \App\Vurb\Middleware\RequireTenantAccess::class,
        \App\Vurb\Middleware\LogToolExecution::class,
        \Vinkius\Vurb\Middleware\RequirePermission::class . ':admin.access',
    ];
}


// ═══════════════════════════════════════════════════════════════
// TOOL: With Per-Tool Middleware Override
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Tools\Admin;

use Vinkius\Vurb\Attributes\{Param, Description, Instructions};
use Vinkius\Vurb\Tools\VurbMutation;

#[Description('Ban a user from the platform')]
#[Instructions('DESTRUCTIVE: Always confirm with an admin before banning. This disables the user account.')]
class BanUser extends VurbMutation
{
    /**
     * Per-tool middleware — ADDED to router middleware.
     * Final chain: RequireTenantAccess → LogToolExecution → RequirePermission:admin.access → RequirePermission:admin.ban
     */
    public array $middleware = [
        \Vinkius\Vurb\Middleware\RequirePermission::class . ':admin.ban',
    ];

    public function handle(
        #[Param(description: 'User ID to ban', example: 'usr_abc123')]
        string $user_id,

        #[Param(description: 'Reason for the ban')]
        string $reason,
    ): array {
        $user = \App\Models\User::findOrFail($user_id);
        $user->update(['banned_at' => now(), 'ban_reason' => $reason]);

        return ['banned' => true, 'user_id' => $user_id];
    }
}


// ═══════════════════════════════════════════════════════════════
// GLOBAL MIDDLEWARE CONFIGURATION
// ═══════════════════════════════════════════════════════════════

// In config/vurb.php:
//
// 'middleware' => [
//     \App\Vurb\Middleware\ValidateJsonInput::class,
//     \Vinkius\Vurb\Middleware\RateLimitVurb::class,
// ],
//
// Global middleware runs BEFORE router and per-tool middleware.
// Final execution order for admin.ban_user:
//   1. ValidateJsonInput (global)
//   2. RateLimitVurb (global)
//   3. RequireTenantAccess (router)
//   4. LogToolExecution (router)
//   5. RequirePermission:admin.access (router)
//   6. RequirePermission:admin.ban (per-tool)
//   7. BanUser::handle()
