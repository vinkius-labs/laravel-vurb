<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Events\DaemonStarted;
use Vinkius\Vurb\Events\DaemonStopped;
use Vinkius\Vurb\Events\ManifestCompiled;
use Vinkius\Vurb\Events\StateInvalidated;
use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;
use Vinkius\Vurb\Exceptions\DaemonNotRunningException;
use Vinkius\Vurb\Exceptions\ManifestCompilationException;
use Vinkius\Vurb\Exceptions\ToolNotFoundException;
use Vinkius\Vurb\Exceptions\VurbException;
use Vinkius\Vurb\Tests\TestCase;

class EventsExceptionsTest extends TestCase
{
    // ═══ Events ═══

    public function test_tool_executed_event_properties(): void
    {
        $event = new ToolExecuted(
            toolName: 'customers.get_profile',
            input: ['id' => 1],
            latencyMs: 42.5,
            presenterName: 'CustomerPresenter',
            systemRules: ['Be polite'],
            isError: false,
        );

        $this->assertSame('customers.get_profile', $event->toolName);
        $this->assertSame(['id' => 1], $event->input);
        $this->assertSame(42.5, $event->latencyMs);
        $this->assertSame('CustomerPresenter', $event->presenterName);
        $this->assertSame(['Be polite'], $event->systemRules);
        $this->assertFalse($event->isError);
    }

    public function test_tool_failed_event_properties(): void
    {
        $event = new ToolFailed(
            toolName: 'process-payment',
            input: ['amount' => 100],
            error: 'Card declined',
            latencyMs: 10.2,
        );

        $this->assertSame('process-payment', $event->toolName);
        $this->assertSame('Card declined', $event->error);
        $this->assertSame(10.2, $event->latencyMs);
    }

    public function test_daemon_started_event_properties(): void
    {
        $event = new DaemonStarted(pid: 5678, transport: 'sse');

        $this->assertSame(5678, $event->pid);
        $this->assertSame('sse', $event->transport);
    }

    public function test_daemon_stopped_event_properties(): void
    {
        $event = new DaemonStopped(pid: 5678);
        $this->assertSame(5678, $event->pid);
    }

    public function test_daemon_stopped_event_null_pid(): void
    {
        $event = new DaemonStopped(pid: null);
        $this->assertNull($event->pid);
    }

    public function test_manifest_compiled_event_properties(): void
    {
        $event = new ManifestCompiled(path: '/tmp/manifest.json', toolCount: 12);

        $this->assertSame('/tmp/manifest.json', $event->path);
        $this->assertSame(12, $event->toolCount);
    }

    public function test_state_invalidated_event_properties(): void
    {
        $event = new StateInvalidated(pattern: 'customers.*', trigger: 'update-customer');

        $this->assertSame('customers.*', $event->pattern);
        $this->assertSame('update-customer', $event->trigger);
    }

    // ═══ Exceptions ═══

    public function test_vurb_exception_extends_runtime_exception(): void
    {
        $e = new VurbException('test');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function test_tool_not_found_exception_extends_vurb_exception(): void
    {
        $e = new ToolNotFoundException('not found');
        $this->assertInstanceOf(VurbException::class, $e);
        $this->assertSame('not found', $e->getMessage());
    }

    public function test_manifest_compilation_exception_extends_vurb_exception(): void
    {
        $e = new ManifestCompilationException('compile error');
        $this->assertInstanceOf(VurbException::class, $e);
    }

    public function test_daemon_not_running_exception_extends_vurb_exception(): void
    {
        $e = new DaemonNotRunningException('daemon down');
        $this->assertInstanceOf(VurbException::class, $e);
    }
}
