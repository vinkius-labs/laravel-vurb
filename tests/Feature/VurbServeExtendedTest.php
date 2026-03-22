<?php

namespace Vinkius\Vurb\Tests\Feature;

use Mockery;
use Symfony\Component\Process\Process as SymfonyProcess;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Tests\TestCase;

class VurbServeExtendedTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- handle() with empty token ---

    public function test_serve_fails_when_token_is_empty(): void
    {
        $this->app['config']->set('vurb.internal_token', '');

        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(true);
        $this->app->instance(DaemonManager::class, $daemon);

        $this->artisan('vurb:serve')
            ->expectsOutputToContain('VURB_INTERNAL_TOKEN')
            ->assertExitCode(1);
    }

    // --- handle() with --no-manifest flag ---

    public function test_serve_with_no_manifest_skips_compilation(): void
    {
        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(true);
        $daemon->shouldReceive('getNodeVersion')->andReturn('v20.0.0');

        $process = Mockery::mock(SymfonyProcess::class);
        $process->shouldReceive('getIncrementalOutput')->andReturn('VURB_DAEMON_READY');
        $process->shouldReceive('isRunning')->andReturn(false); // Immediately exit
        $process->shouldReceive('getExitCode')->andReturn(0);
        $process->shouldReceive('getErrorOutput')->andReturn('');

        $daemon->shouldReceive('start')->once()->andReturn($process);
        $daemon->shouldReceive('stop')->once();

        $this->app->instance(DaemonManager::class, $daemon);

        // ManifestCompiler should NOT be called because --no-manifest
        $compiler = Mockery::mock(ManifestCompiler::class);
        $compiler->shouldNotReceive('compileAndWrite');
        $this->app->instance(ManifestCompiler::class, $compiler);

        $this->app['config']->set('vurb.observability.events', false);

        $this->artisan('vurb:serve', ['--no-manifest' => true])
            ->assertExitCode(0);
    }

    // --- handle() with --port flag ---

    public function test_serve_with_port_flag_passes_env_override(): void
    {
        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(true);
        $daemon->shouldReceive('getNodeVersion')->andReturn('v20.0.0');

        $process = Mockery::mock(SymfonyProcess::class);
        $process->shouldReceive('getIncrementalOutput')->andReturn('VURB_DAEMON_READY');
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('getExitCode')->andReturn(0);
        $process->shouldReceive('getErrorOutput')->andReturn('');

        $daemon->shouldReceive('start')
            ->once()
            ->withArgs(function (array $envOverrides) {
                return isset($envOverrides['VURB_PORT']) && $envOverrides['VURB_PORT'] === '8080';
            })
            ->andReturn($process);
        $daemon->shouldReceive('stop')->once();

        $this->app->instance(DaemonManager::class, $daemon);

        $compiler = Mockery::mock(ManifestCompiler::class);
        $compiler->shouldReceive('compileAndWrite')->once();
        $this->app->instance(ManifestCompiler::class, $compiler);

        $this->app['config']->set('vurb.observability.events', false);

        $this->artisan('vurb:serve', ['--port' => '8080'])
            ->assertExitCode(0);
    }

    // --- handle() with --transport flag ---

    public function test_serve_with_transport_flag_passes_env_override(): void
    {
        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(true);
        $daemon->shouldReceive('getNodeVersion')->andReturn('v20.0.0');

        $process = Mockery::mock(SymfonyProcess::class);
        $process->shouldReceive('getIncrementalOutput')->andReturn('VURB_DAEMON_READY');
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('getExitCode')->andReturn(0);
        $process->shouldReceive('getErrorOutput')->andReturn('');

        $daemon->shouldReceive('start')
            ->once()
            ->withArgs(function (array $envOverrides) {
                return isset($envOverrides['VURB_TRANSPORT']) && $envOverrides['VURB_TRANSPORT'] === 'sse';
            })
            ->andReturn($process);
        $daemon->shouldReceive('stop')->once();

        $this->app->instance(DaemonManager::class, $daemon);

        $compiler = Mockery::mock(ManifestCompiler::class);
        $compiler->shouldReceive('compileAndWrite')->once();
        $this->app->instance(ManifestCompiler::class, $compiler);

        $this->app['config']->set('vurb.observability.events', false);

        $this->artisan('vurb:serve', ['--transport' => 'sse'])
            ->assertExitCode(0);
    }

    // --- Daemon exits with non-zero → FAILURE ---

    public function test_serve_fails_when_daemon_exits_non_zero(): void
    {
        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(true);
        $daemon->shouldReceive('getNodeVersion')->andReturn('v20.0.0');

        $process = Mockery::mock(SymfonyProcess::class);
        $process->shouldReceive('getIncrementalOutput')->andReturn('');
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('getExitCode')->andReturn(1);
        $process->shouldReceive('getErrorOutput')->andReturn('Fatal error occurred');

        $daemon->shouldReceive('start')->once()->andReturn($process);
        $daemon->shouldReceive('stop')->once();

        $this->app->instance(DaemonManager::class, $daemon);

        $compiler = Mockery::mock(ManifestCompiler::class);
        $compiler->shouldReceive('compileAndWrite')->once();
        $this->app->instance(ManifestCompiler::class, $compiler);

        $this->app['config']->set('vurb.observability.events', false);

        $this->artisan('vurb:serve')
            ->assertExitCode(1);
    }
}
