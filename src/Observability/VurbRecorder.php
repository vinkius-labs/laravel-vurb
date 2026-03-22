<?php

namespace Vinkius\Vurb\Observability;

use Vinkius\Vurb\Events\ToolExecuted;

/**
 * Pulse Recorder for Vurb tool executions.
 *
 * Records real-time metrics for the Laravel Pulse dashboard.
 * Only activated when Pulse is installed and observability is enabled.
 */
class VurbRecorder
{
    /**
     * Register the recorder with the event dispatcher.
     */
    public function register($app): void
    {
        if (! class_exists(\Laravel\Pulse\Facades\Pulse::class)) {
            return;
        }

        if (! config('vurb.observability.pulse', true)) {
            return;
        }

        $app['events']->listen(ToolExecuted::class, function (ToolExecuted $event) {
            \Laravel\Pulse\Facades\Pulse::record(
                type: 'vurb_tool_execution',
                key: $event->toolName,
                value: $event->latencyMs,
            )->avg()->count();
        });
    }
}
