<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\Log;
use Vinkius\Vurb\Events\DaemonStarted;
use Vinkius\Vurb\Events\DaemonStopped;
use Vinkius\Vurb\Events\ManifestCompiled;
use Vinkius\Vurb\Events\StateInvalidated;
use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;
use Vinkius\Vurb\Observability\TelemetryListener;
use Vinkius\Vurb\Tests\TestCase;

class ObservabilityTest extends TestCase
{
    public function test_telemetry_listener_subscribes_to_all_events(): void
    {
        $listener = new TelemetryListener();

        $events = $this->createMock(\Illuminate\Events\Dispatcher::class);
        $events->expects($this->exactly(6))->method('listen');

        $listener->subscribe($events);
    }

    public function test_on_tool_executed_logs_debug(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'Tool executed'));

        $listener = new TelemetryListener();
        $listener->onToolExecuted(new ToolExecuted(
            toolName: 'test-tool',
            input: ['a' => 1],
            latencyMs: 12.5,
            presenterName: null,
            systemRules: [],
            isError: false,
        ));
    }

    public function test_on_tool_failed_logs_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'Tool failed'));

        $listener = new TelemetryListener();
        $listener->onToolFailed(new ToolFailed(
            toolName: 'fail-tool',
            input: [],
            error: 'Something broke',
            latencyMs: 5.0,
        ));
    }

    public function test_on_daemon_started_logs_info(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'Daemon started'));

        $listener = new TelemetryListener();
        $listener->onDaemonStarted(new DaemonStarted(pid: 1234, transport: 'stdio'));
    }

    public function test_on_daemon_stopped_logs_info(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'Daemon stopped'));

        $listener = new TelemetryListener();
        $listener->onDaemonStopped(new DaemonStopped(pid: 1234));
    }

    public function test_on_manifest_compiled_logs_info(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'Manifest compiled'));

        $listener = new TelemetryListener();
        $listener->onManifestCompiled(new ManifestCompiled(path: '/tmp/manifest.json', toolCount: 5));
    }

    public function test_on_state_invalidated_logs_debug(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'State invalidated'));

        $listener = new TelemetryListener();
        $listener->onStateInvalidated(new StateInvalidated(pattern: 'billing.*', trigger: 'update'));
    }
}
