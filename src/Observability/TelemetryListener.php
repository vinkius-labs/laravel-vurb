<?php

namespace Vinkius\Vurb\Observability;

use Illuminate\Support\Facades\Log;
use Vinkius\Vurb\Events\DaemonStarted;
use Vinkius\Vurb\Events\DaemonStopped;
use Vinkius\Vurb\Events\ManifestCompiled;
use Vinkius\Vurb\Events\StateInvalidated;
use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;

/**
 * Centralized telemetry listener for Vurb events.
 * Provides structured logging for all lifecycle events.
 */
class TelemetryListener
{
    public function subscribe($events): void
    {
        $events->listen(ToolExecuted::class, [$this, 'onToolExecuted']);
        $events->listen(ToolFailed::class, [$this, 'onToolFailed']);
        $events->listen(DaemonStarted::class, [$this, 'onDaemonStarted']);
        $events->listen(DaemonStopped::class, [$this, 'onDaemonStopped']);
        $events->listen(ManifestCompiled::class, [$this, 'onManifestCompiled']);
        $events->listen(StateInvalidated::class, [$this, 'onStateInvalidated']);
    }

    public function onToolExecuted(ToolExecuted $event): void
    {
        Log::debug('[Vurb] Tool executed', [
            'tool' => $event->toolName,
            'latency_ms' => $event->latencyMs,
            'presenter' => $event->presenterName,
            'is_error' => $event->isError,
        ]);
    }

    public function onToolFailed(ToolFailed $event): void
    {
        Log::warning('[Vurb] Tool failed', [
            'tool' => $event->toolName,
            'error' => $event->error,
            'latency_ms' => $event->latencyMs,
        ]);
    }

    public function onDaemonStarted(DaemonStarted $event): void
    {
        Log::info('[Vurb] Daemon started', [
            'pid' => $event->pid,
            'transport' => $event->transport,
        ]);
    }

    public function onDaemonStopped(DaemonStopped $event): void
    {
        Log::info('[Vurb] Daemon stopped', [
            'pid' => $event->pid,
        ]);
    }

    public function onManifestCompiled(ManifestCompiled $event): void
    {
        Log::info('[Vurb] Manifest compiled', [
            'path' => $event->path,
            'tool_count' => $event->toolCount,
        ]);
    }

    public function onStateInvalidated(StateInvalidated $event): void
    {
        Log::debug('[Vurb] State invalidated', [
            'pattern' => $event->pattern,
            'trigger' => $event->trigger,
        ]);
    }
}
