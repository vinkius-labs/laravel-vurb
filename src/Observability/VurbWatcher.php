<?php

namespace Vinkius\Vurb\Observability;

use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;

/**
 * Telescope Watcher for Vurb tool executions.
 *
 * This class integrates with Laravel Telescope to record all MCP tool calls.
 * It is only activated when Telescope is installed and observability is enabled.
 */
class VurbWatcher
{
    /**
     * Register the watcher with the event dispatcher.
     */
    public function register($app): void
    {
        if (! class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        if (! config('vurb.observability.telescope', true)) {
            return;
        }

        $app['events']->listen(ToolExecuted::class, function (ToolExecuted $event) {
            \Laravel\Telescope\Telescope::recordEvent(
                \Laravel\Telescope\IncomingEntry::make([
                    'type' => 'vurb-tool',
                    'tool' => $event->toolName,
                    'input' => $event->input,
                    'latency_ms' => $event->latencyMs,
                    'presenter' => $event->presenterName,
                    'system_rules_count' => count($event->systemRules),
                    'is_error' => $event->isError,
                ])
            );
        });

        $app['events']->listen(ToolFailed::class, function (ToolFailed $event) {
            \Laravel\Telescope\Telescope::recordEvent(
                \Laravel\Telescope\IncomingEntry::make([
                    'type' => 'vurb-tool-error',
                    'tool' => $event->toolName,
                    'input' => $event->input,
                    'error' => $event->error,
                    'latency_ms' => $event->latencyMs,
                ])
            );
        });
    }
}
