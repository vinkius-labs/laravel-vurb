<?php

namespace Vinkius\Vurb\Services;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;
use Vinkius\Vurb\Events\DaemonStarted;
use Vinkius\Vurb\Events\DaemonStopped;
use Vinkius\Vurb\Exceptions\DaemonNotRunningException;

class DaemonManager
{
    protected ?SymfonyProcess $process = null;

    /**
     * Get the path to the daemon entry script (TypeScript source bundled with the package).
     */
    public function getDaemonScriptPath(): string
    {
        return dirname(__DIR__, 2) . '/bin/daemon/src/bridge.ts';
    }

    /**
     * Get the path to the daemon's package.json.
     */
    public function getDaemonPackagePath(): string
    {
        return dirname(__DIR__, 2) . '/bin/daemon';
    }

    /**
     * Resolve the npx binary path.
     */
    public function resolveNpxPath(): string
    {
        $custom = config('vurb.daemon.npx_path');
        if ($custom !== null && $custom !== '') {
            return $custom;
        }

        // Auto-detect: try npx in PATH
        return PHP_OS_FAMILY === 'Windows' ? 'npx.cmd' : 'npx';
    }

    /**
     * Resolve the node binary path.
     */
    public function resolveNodePath(): string
    {
        $custom = config('vurb.daemon.node_path');
        if ($custom !== null && $custom !== '') {
            return $custom;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'node.exe' : 'node';
    }

    /**
     * Check if Node.js/npx is available on the system.
     */
    public function isNodeAvailable(): bool
    {
        $process = new SymfonyProcess([$this->resolveNodePath(), '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Get the installed Node.js version.
     */
    public function getNodeVersion(): ?string
    {
        $process = new SymfonyProcess([$this->resolveNodePath(), '--version']);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    /**
     * Install daemon npm dependencies.
     */
    public function installDependencies(): bool
    {
        $daemonPath = $this->getDaemonPackagePath();

        $process = new SymfonyProcess(
            ['npm', 'install', '--production'],
            $daemonPath,
            timeout: 120,
        );

        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Build the environment variables for the daemon process.
     */
    public function buildEnv(array $overrides = []): array
    {
        $env = [
            'VURB_MANIFEST_PATH' => config('vurb.daemon.manifest_path'),
            'VURB_INTERNAL_TOKEN' => config('vurb.internal_token'),
            'VURB_BRIDGE_URL' => config('vurb.bridge.base_url'),
            'VURB_TRANSPORT' => config('vurb.transport', 'stdio'),
            'NODE_ENV' => app()->environment('production') ? 'production' : 'development',
        ];

        $port = config('vurb.daemon.port');
        if ($port !== null) {
            $env['VURB_PORT'] = (string) $port;
        }

        return array_merge($env, $overrides);
    }

    /**
     * Build the full command to start the daemon via npx/tsx.
     */
    public function buildCommand(): array
    {
        $scriptPath = $this->getDaemonScriptPath();

        // Use npx tsx to run TypeScript directly
        return [$this->resolveNpxPath(), 'tsx', $scriptPath];
    }

    /**
     * Start the daemon process.
     */
    public function start(array $envOverrides = [], ?\Closure $onOutput = null): SymfonyProcess
    {
        $command = $this->buildCommand();
        $env = $this->buildEnv($envOverrides);
        $cwd = $this->getDaemonPackagePath();

        $this->process = new SymfonyProcess(
            command: $command,
            cwd: $cwd,
            env: array_merge($_ENV, $env),
            timeout: null,
        );

        if ($onOutput !== null) {
            $this->process->start(function ($type, $buffer) use ($onOutput) {
                $onOutput($type, $buffer);
            });
        } else {
            $this->process->start();
        }

        if (config('vurb.observability.events', true)) {
            event(new DaemonStarted(
                pid: $this->process->getPid(),
                transport: config('vurb.transport', 'stdio'),
            ));
        }

        return $this->process;
    }

    /**
     * Stop the daemon process gracefully.
     */
    public function stop(int $timeout = 10): void
    {
        if ($this->process === null || ! $this->process->isRunning()) {
            return;
        }

        $pid = $this->process->getPid();
        $this->process->stop($timeout);

        if (config('vurb.observability.events', true)) {
            event(new DaemonStopped(pid: $pid));
        }

        $this->process = null;
    }

    /**
     * Check if the daemon is currently running.
     */
    public function isRunning(): bool
    {
        return $this->process !== null && $this->process->isRunning();
    }

    /**
     * Get daemon process info.
     */
    public function getProcessInfo(): ?array
    {
        if ($this->process === null) {
            return null;
        }

        return [
            'pid' => $this->process->getPid(),
            'running' => $this->process->isRunning(),
            'exit_code' => $this->process->getExitCode(),
        ];
    }

    /**
     * Wait for the daemon to emit its READY signal.
     */
    public function waitForReady(int $timeoutMs = 10000): bool
    {
        if ($this->process === null) {
            return false;
        }

        $start = hrtime(true);
        $output = '';

        while ((hrtime(true) - $start) / 1e6 < $timeoutMs) {
            $output .= $this->process->getIncrementalOutput();

            if (str_contains($output, 'VURB_DAEMON_READY')) {
                return true;
            }

            if (! $this->process->isRunning()) {
                return false;
            }

            usleep(50000); // 50ms
        }

        return false;
    }

    /**
     * Get the underlying Symfony process.
     */
    public function getProcess(): ?SymfonyProcess
    {
        return $this->process;
    }

    /**
     * Forward stdin to the daemon process (for stdio transport).
     */
    public function writeStdin(string $data): void
    {
        if ($this->process === null || ! $this->process->isRunning()) {
            throw new DaemonNotRunningException('Daemon is not running.');
        }

        $this->process->getInput()?->write($data);
    }
}
