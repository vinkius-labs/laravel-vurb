<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process as SymfonyProcess;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\ManifestCompiler;

class VurbServeCommand extends Command
{
    protected $signature = 'vurb:serve
        {--port= : Daemon port override}
        {--transport= : Transport mode (stdio|sse|streamable-http)}
        {--dev : Enable dev mode with manifest recompilation}
        {--no-manifest : Skip manifest compilation}';

    protected $description = 'Compile the manifest and start the Vurb MCP daemon';

    protected bool $shouldStop = false;

    public function handle(ManifestCompiler $compiler, DaemonManager $daemon): int
    {
        // 1. Pre-flight checks
        if (! $daemon->isNodeAvailable()) {
            $this->components->error('Node.js not found. Please install Node.js 18+ and run: php artisan vurb:install');
            return self::FAILURE;
        }

        if (empty(config('vurb.internal_token'))) {
            $this->components->error('VURB_INTERNAL_TOKEN not set. Run: php artisan vurb:install');
            return self::FAILURE;
        }

        // 2. Compile manifest
        if (! $this->option('no-manifest')) {
            $this->compileManifest($compiler);
        }

        // 3. Build env overrides
        $envOverrides = [];

        if ($this->option('port')) {
            $envOverrides['VURB_PORT'] = $this->option('port');
        }

        if ($this->option('transport')) {
            $envOverrides['VURB_TRANSPORT'] = $this->option('transport');
        }

        // 4. Start daemon
        $transport = $this->option('transport') ?? config('vurb.transport', 'stdio');
        $port = $this->option('port') ?? config('vurb.daemon.port', '-');
        $nodeVersion = $daemon->getNodeVersion();

        $this->newLine();
        $this->components->info("Starting Vurb MCP daemon...");
        $this->components->twoColumnDetail('Transport', $transport);
        $this->components->twoColumnDetail('Port', (string) $port);
        $this->components->twoColumnDetail('Node.js', $nodeVersion ?? 'unknown');
        $this->components->twoColumnDetail('Bridge', config('vurb.bridge.base_url'));
        $this->newLine();

        // Register signal handlers for graceful shutdown
        $this->registerSignalHandlers();

        $process = $daemon->start($envOverrides, function ($type, $buffer) {
            if ($type === SymfonyProcess::OUT) {
                $this->output->write($buffer);
            } else {
                $this->output->write("<fg=yellow>{$buffer}</>");
            }
        });

        // 5. Wait for the daemon process or signal
        $readyDetected = $this->waitForReady($process);

        if ($readyDetected) {
            $this->components->info('Daemon is ready. Listening for MCP connections...');
            $this->newLine();
        }

        // 6. Keep running until the daemon exits or interrupted
        while ($process->isRunning() && ! $this->shouldStop) {
            usleep(100_000); // 100ms
        }

        // 7. Shutdown
        $daemon->stop();

        if ($process->getExitCode() !== 0 && ! $this->shouldStop) {
            $this->components->error('Daemon exited unexpectedly (code: ' . $process->getExitCode() . ')');
            $stderr = $process->getErrorOutput();
            if ($stderr) {
                $this->error($stderr);
            }
            return self::FAILURE;
        }

        $this->components->info('Daemon stopped.');
        return self::SUCCESS;
    }

    protected function compileManifest(ManifestCompiler $compiler): void
    {
        $this->components->task('Compiling Schema Manifest', function () use ($compiler) {
            $compiler->compileAndWrite();
        });
    }

    protected function waitForReady(SymfonyProcess $process, int $timeoutMs = 15_000): bool
    {
        $start = hrtime(true);
        $deadline = $start + ($timeoutMs * 1_000_000);

        while (hrtime(true) < $deadline && $process->isRunning()) {
            $output = $process->getIncrementalOutput();
            if (str_contains($output, 'VURB_DAEMON_READY')) {
                return true;
            }
            usleep(50_000); // 50ms
        }

        return false;
    }

    protected function registerSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }
    }
}
