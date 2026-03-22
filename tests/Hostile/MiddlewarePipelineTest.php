<?php

namespace Vinkius\Vurb\Tests\Hostile;

use Vinkius\Vurb\Testing\FakeVurbTester;
use Vinkius\Vurb\Tests\Fixtures\BlockingMiddleware;
use Vinkius\Vurb\Tests\Fixtures\MutatingMiddleware;
use Vinkius\Vurb\Tests\Fixtures\ThrowingMiddleware;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\TestCase;

/**
 * Tests the VurbMiddleware pipeline under adversarial conditions:
 * exception in middleware, middleware that blocks (never calls $next),
 * middleware that mutates context, middleware ordering, 
 * and middleware with parameters.
 */
class MiddlewarePipelineTest extends TestCase
{
    // ─── Throwing Middleware ───

    public function test_exception_in_middleware_is_caught(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([ThrowingMiddleware::class])
            ->call(['id' => 1]);

        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
        $this->assertSame('Middleware explosion', $result->errorMessage);
    }

    // ─── Blocking Middleware ───

    public function test_blocking_middleware_returns_error(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([BlockingMiddleware::class])
            ->call(['id' => 1]);

        $this->assertTrue($result->isError);
        $this->assertSame('BLOCKED', $result->errorCode);
        $this->assertSame('Request blocked by middleware.', $result->errorMessage);
    }

    // ─── Empty Middleware Pipeline ───

    public function test_empty_middleware_pipeline_succeeds(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([])
            ->call(['id' => 1]);

        $this->assertFalse($result->isError);
        $this->assertSame(1, $result->data['id']);
    }

    // ─── Multiple Middleware Sequential ───

    public function test_multiple_middleware_execute_in_order(): void
    {
        // MutatingMiddleware passes through, ThrowingMiddleware explodes
        // Since pipeline wraps in reverse, the last one added is outermost
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([MutatingMiddleware::class, ThrowingMiddleware::class])
            ->call(['id' => 1]);

        // ThrowingMiddleware should throw before tool executes
        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
    }

    // ─── Middleware + Successful Tool ───

    public function test_middleware_that_passes_still_allows_tool(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([MutatingMiddleware::class])
            ->call(['id' => 42]);

        $this->assertFalse($result->isError);
        $this->assertSame(42, $result->data['id']);
    }

    // ─── addMiddleware ───

    public function test_add_middleware_appends(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([])
            ->addMiddleware(ThrowingMiddleware::class)
            ->call(['id' => 1]);

        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
    }

    // ─── Middleware Isolation (one request's middleware doesn't affect another) ───

    public function test_middleware_is_isolated_per_tester(): void
    {
        $successResult = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([])
            ->call(['id' => 1]);

        $failResult = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([ThrowingMiddleware::class])
            ->call(['id' => 1]);

        $this->assertFalse($successResult->isError);
        $this->assertTrue($failResult->isError);
    }

    // ─── Blocking Middleware Prevents Tool Execution ───

    public function test_blocking_middleware_tool_never_executes(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([BlockingMiddleware::class])
            ->call(['id' => 1]);

        // Tool returns data with 'id' key on success, but middleware blocked
        $this->assertTrue($result->isError);
        // Data should not contain tool output
        if (is_array($result->data)) {
            $this->assertArrayNotHasKey('name', $result->data);
        }
    }
}
