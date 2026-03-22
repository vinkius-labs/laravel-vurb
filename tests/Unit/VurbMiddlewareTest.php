<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Vinkius\Vurb\Middleware\AuditTrail;
use Vinkius\Vurb\Middleware\RateLimitVurb;
use Vinkius\Vurb\Middleware\RequirePermission;
use Vinkius\Vurb\Tests\TestCase;

class VurbMiddlewareTest extends TestCase
{
    // ═══ RequirePermission ═══

    public function test_require_permission_returns_unauthorized_when_user_null(): void
    {
        $middleware = new RequirePermission();

        $result = $middleware->handle(
            ['user' => null],
            fn ($ctx) => 'ok',
            'manage-users',
        );

        $this->assertIsArray($result);
        $this->assertSame('UNAUTHORIZED', $result['code']);
        $this->assertTrue($result['error']);
    }

    public function test_require_permission_returns_forbidden_when_gate_denies(): void
    {
        $user = new \Illuminate\Foundation\Auth\User();
        $user->id = 1;

        Gate::define('manage-users', fn () => false);

        $middleware = new RequirePermission();
        $result = $middleware->handle(
            ['user' => $user],
            fn ($ctx) => 'ok',
            'manage-users',
        );

        $this->assertIsArray($result);
        $this->assertSame('FORBIDDEN', $result['code']);
        $this->assertStringContainsString('manage-users', $result['message']);
    }

    public function test_require_permission_passes_when_gate_allows(): void
    {
        $user = new \Illuminate\Foundation\Auth\User();
        $user->id = 1;

        Gate::define('manage-users', fn () => true);

        $middleware = new RequirePermission();
        $result = $middleware->handle(
            ['user' => $user],
            fn ($ctx) => 'ok',
            'manage-users',
        );

        $this->assertSame('ok', $result);
    }

    public function test_require_permission_checks_multiple_permissions(): void
    {
        $user = new \Illuminate\Foundation\Auth\User();
        $user->id = 1;

        Gate::define('manage-users', fn () => true);
        Gate::define('delete-users', fn () => false);

        $middleware = new RequirePermission();
        $result = $middleware->handle(
            ['user' => $user],
            fn ($ctx) => 'ok',
            'manage-users', 'delete-users',
        );

        $this->assertIsArray($result);
        $this->assertSame('FORBIDDEN', $result['code']);
        $this->assertStringContainsString('delete-users', $result['message']);
    }

    public function test_require_permission_all_permissions_pass(): void
    {
        $user = new \Illuminate\Foundation\Auth\User();
        $user->id = 1;

        Gate::define('read', fn () => true);
        Gate::define('write', fn () => true);

        $middleware = new RequirePermission();
        $result = $middleware->handle(
            ['user' => $user],
            fn ($ctx) => 'ok',
            'read', 'write',
        );

        $this->assertSame('ok', $result);
    }

    // ═══ RateLimitVurb ═══

    public function test_rate_limit_allows_under_limit(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturn(false);
        RateLimiter::shouldReceive('hit')
            ->once();

        $user = new class {
            public function getAuthIdentifier(): int { return 42; }
        };

        $middleware = new RateLimitVurb();
        $result = $middleware->handle(
            ['tool' => 'test-tool', 'user' => $user],
            fn ($ctx) => 'ok',
        );

        $this->assertSame('ok', $result);
    }

    public function test_rate_limit_blocks_over_limit(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturn(true);
        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->andReturn(30);

        $user = new class {
            public function getAuthIdentifier(): int { return 42; }
        };

        $middleware = new RateLimitVurb();
        $result = $middleware->handle(
            ['tool' => 'test-tool', 'user' => $user],
            fn ($ctx) => 'ok',
        );

        $this->assertIsArray($result);
        $this->assertSame('RATE_LIMITED', $result['code']);
        $this->assertSame(30, $result['retryAfter']);
        $this->assertTrue($result['error']);
    }

    public function test_rate_limit_uses_anonymous_when_no_user(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->withArgs(function (string $key) {
                return str_contains($key, 'anonymous');
            })
            ->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        $middleware = new RateLimitVurb();
        $result = $middleware->handle(
            ['tool' => 'test-tool', 'user' => null],
            fn ($ctx) => 'ok',
        );

        $this->assertSame('ok', $result);
    }

    public function test_rate_limit_key_includes_tool_and_user(): void
    {
        $capturedKey = null;

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->withArgs(function (string $key) use (&$capturedKey) {
                $capturedKey = $key;
                return true;
            })
            ->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        $user = new class {
            public function getAuthIdentifier(): int { return 99; }
        };

        $middleware = new RateLimitVurb();
        $middleware->handle(
            ['tool' => 'payments.process', 'user' => $user],
            fn ($ctx) => 'ok',
        );

        $this->assertSame('vurb:payments.process:99', $capturedKey);
    }

    // ═══ AuditTrail ═══

    public function test_audit_trail_logs_execution(): void
    {
        Log::shouldReceive('channel->info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Vurb tool executed'
                    && $context['tool'] === 'test-tool'
                    && $context['is_error'] === false
                    && array_key_exists('latency_ms', $context)
                    && $context['user_id'] === 1;
            });

        $user = new class {
            public function getAuthIdentifier(): int { return 1; }
        };

        $middleware = new AuditTrail();
        $result = $middleware->handle(
            ['tool' => 'test-tool', 'input' => ['foo' => 'bar'], 'user' => $user],
            fn ($ctx) => ['data' => 'success'],
        );

        $this->assertSame(['data' => 'success'], $result);
    }

    public function test_audit_trail_detects_error_result(): void
    {
        Log::shouldReceive('channel->info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $context['is_error'] === true && $context['user_id'] === null;
            });

        $middleware = new AuditTrail();
        $result = $middleware->handle(
            ['tool' => 'test-tool', 'input' => [], 'user' => null],
            fn ($ctx) => ['error' => true, 'code' => 'FAIL'],
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
    }

    public function test_audit_trail_logs_latency(): void
    {
        Log::shouldReceive('channel->info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $context['latency_ms'] >= 0;
            });

        $middleware = new AuditTrail();
        $middleware->handle(
            ['tool' => 'slow-tool', 'input' => ['x' => 1], 'user' => null],
            fn ($ctx) => ['done' => true],
        );
    }
}
