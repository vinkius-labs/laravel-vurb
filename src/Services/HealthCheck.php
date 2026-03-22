<?php

namespace Vinkius\Vurb\Services;

use Illuminate\Support\Facades\Http;

class HealthCheck
{
    public function __construct(
        protected DaemonManager $daemon,
        protected ToolDiscovery $discovery,
    ) {}

    /**
     * Run a full health check.
     */
    public function check(): bool
    {
        return $this->isDaemonRunning() && $this->isBridgeReachable();
    }

    /**
     * Get detailed health status.
     */
    public function status(): array
    {
        $tools = $this->discovery->discover();
        $nodeVersion = $this->daemon->getNodeVersion();

        return [
            'daemon' => [
                'running' => $this->isDaemonRunning(),
                'process' => $this->daemon->getProcessInfo(),
            ],
            'node' => [
                'available' => $this->daemon->isNodeAvailable(),
                'version' => $nodeVersion,
            ],
            'bridge' => [
                'reachable' => $this->isBridgeReachable(),
                'base_url' => config('vurb.bridge.base_url'),
            ],
            'tools' => [
                'count' => count($tools),
                'names' => array_keys($tools),
            ],
            'config' => [
                'transport' => config('vurb.transport'),
                'token_set' => ! empty(config('vurb.internal_token')),
                'manifest_path' => config('vurb.daemon.manifest_path'),
            ],
        ];
    }

    /**
     * Check if the daemon process is running.
     */
    public function isDaemonRunning(): bool
    {
        return $this->daemon->isRunning();
    }

    /**
     * Check if the bridge endpoint is reachable.
     */
    public function isBridgeReachable(): bool
    {
        $baseUrl = config('vurb.bridge.base_url');
        $prefix = config('vurb.bridge.prefix', '/_vurb');
        $token = config('vurb.internal_token');

        try {
            $response = Http::timeout(3)
                ->withHeaders(['X-Vurb-Token' => $token])
                ->get("{$baseUrl}{$prefix}/health");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
