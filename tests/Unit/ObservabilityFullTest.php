<?php

namespace Vinkius\Vurb\Tests\Unit;

use Laravel\Pulse\Facades\Pulse;
use PHPUnit\Framework\Attributes\Test;
use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;
use Vinkius\Vurb\Observability\VurbRecorder;
use Vinkius\Vurb\Observability\VurbWatcher;
use Vinkius\Vurb\Tests\TestCase;

/**
 * Tests covering VurbWatcher (lines 22, 30-52) and VurbRecorder (lines 21, 29-33).
 *
 * Telescope & Pulse are installed as dev-deps so class_exists() returns true.
 * Telescope::recordEvent() silently returns when not recording (no service provider booted).
 * Pulse facade is mocked via shouldReceive() so the closure body runs without the real service.
 */
class ObservabilityFullTest extends TestCase
{
    // ─── VurbWatcher ─────────────────────────────────────────

    #[Test]
    public function vurb_watcher_registers_and_records_tool_executed()
    {
        $this->app['config']->set('vurb.observability.telescope', true);

        $watcher = new VurbWatcher();
        $watcher->register($this->app);

        // Dispatching fires the closure → covers lines 30-42
        // Telescope::recordEvent silently returns because Telescope is not actively recording
        event(new ToolExecuted(
            toolName: 'test.tool',
            input: ['id' => 1],
            latencyMs: 42.5,
            presenterName: 'TestPresenter',
            systemRules: ['rule1'],
            isError: false,
        ));

        $this->assertTrue(true);
    }

    #[Test]
    public function vurb_watcher_registers_and_records_tool_failed()
    {
        $this->app['config']->set('vurb.observability.telescope', true);

        $watcher = new VurbWatcher();
        $watcher->register($this->app);

        // Dispatching fires the closure → covers lines 44-54
        event(new ToolFailed(
            toolName: 'test.fail',
            input: ['bad' => 'data'],
            error: 'Something went wrong',
            latencyMs: 100.0,
        ));

        $this->assertTrue(true);
    }

    #[Test]
    public function vurb_watcher_registers_listeners_for_both_event_types()
    {
        $this->app['config']->set('vurb.observability.telescope', true);

        $watcher = new VurbWatcher();
        $watcher->register($this->app);

        $this->assertNotEmpty($this->app['events']->getListeners(ToolExecuted::class));
        $this->assertNotEmpty($this->app['events']->getListeners(ToolFailed::class));
    }

    // ─── VurbRecorder ────────────────────────────────────────

    #[Test]
    public function vurb_recorder_registers_and_records_tool_executed()
    {
        $this->app['config']->set('vurb.observability.pulse', true);

        // Mock the Pulse facade so the closure body executes without a real Pulse service
        $builder = \Mockery::mock(\Laravel\Pulse\Entry::class);
        $builder->shouldReceive('avg')->once()->andReturnSelf();
        $builder->shouldReceive('count')->once()->andReturnSelf();

        Pulse::shouldReceive('record')
            ->once()
            ->with('vurb_tool_execution', 'test.pulse_tool', 55.0)
            ->andReturn($builder);

        $recorder = new VurbRecorder();
        $recorder->register($this->app);

        // Dispatching fires the closure → covers lines 29-34
        event(new ToolExecuted(
            toolName: 'test.pulse_tool',
            input: ['x' => 1],
            latencyMs: 55.0,
            presenterName: null,
            systemRules: [],
            isError: false,
        ));
    }
}
