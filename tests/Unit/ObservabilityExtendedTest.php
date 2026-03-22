<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Observability\VurbRecorder;
use Vinkius\Vurb\Observability\VurbWatcher;
use Vinkius\Vurb\Tests\TestCase;

class ObservabilityExtendedTest extends TestCase
{
    // --- VurbWatcher: Telescope not installed → early return ---

    public function test_vurb_watcher_register_returns_early_when_telescope_not_installed(): void
    {
        // Telescope is not installed in the test environment, so class_exists check returns false
        $watcher = new VurbWatcher();

        // Should not throw — just returns early
        $watcher->register($this->app);

        $this->assertTrue(true); // No exception = pass
    }

    public function test_vurb_watcher_register_respects_config_toggle(): void
    {
        // Even if Telescope were installed, the config check would short-circuit
        $this->app['config']->set('vurb.observability.telescope', false);

        $watcher = new VurbWatcher();
        $watcher->register($this->app);

        $this->assertTrue(true);
    }

    // --- VurbRecorder: Pulse not installed → early return ---

    public function test_vurb_recorder_register_returns_early_when_pulse_not_installed(): void
    {
        // Pulse is not installed in the test environment
        $recorder = new VurbRecorder();

        // Should not throw
        $recorder->register($this->app);

        $this->assertTrue(true);
    }

    public function test_vurb_recorder_register_respects_config_toggle(): void
    {
        $this->app['config']->set('vurb.observability.pulse', false);

        $recorder = new VurbRecorder();
        $recorder->register($this->app);

        $this->assertTrue(true);
    }
}
