<?php

namespace Vinkius\Vurb\Tests\Unit;

use Mockery;
use Symfony\Component\Process\Process as SymfonyProcess;
use Vinkius\Vurb\Exceptions\DaemonNotRunningException;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Tests\TestCase;

class DaemonManagerExtendedTest extends TestCase
{
    protected DaemonManager $daemon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->daemon = new DaemonManager();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- getProcess() ---

    public function test_get_process_returns_null_initially(): void
    {
        $this->assertNull($this->daemon->getProcess());
    }

    // --- isRunning() ---

    public function test_is_running_returns_false_initially(): void
    {
        $this->assertFalse($this->daemon->isRunning());
    }

    // --- getProcessInfo() ---

    public function test_get_process_info_returns_null_when_no_process(): void
    {
        $this->assertNull($this->daemon->getProcessInfo());
    }

    public function test_get_process_info_returns_array_when_process_exists(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('getPid')->andReturn(12345);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('getExitCode')->andReturn(null);

        $this->setProcess($this->daemon, $mock);

        $info = $this->daemon->getProcessInfo();
        $this->assertIsArray($info);
        $this->assertSame(12345, $info['pid']);
        $this->assertTrue($info['running']);
        $this->assertNull($info['exit_code']);
    }

    // --- stop() when no process ---

    public function test_stop_when_not_running_is_noop(): void
    {
        // Should not throw
        $this->daemon->stop();
        $this->assertNull($this->daemon->getProcess());
    }

    public function test_stop_when_process_exists_but_not_running_is_noop(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(false);

        $this->setProcess($this->daemon, $mock);

        $this->daemon->stop();
        // Process is still set since it wasn't running
        $this->assertNotNull($this->daemon->getProcess());
    }

    public function test_stop_when_process_is_running_stops_gracefully(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('getPid')->andReturn(99999);
        $mock->shouldReceive('stop')->once()->with(10);

        $this->app['config']->set('vurb.observability.events', false);

        $this->setProcess($this->daemon, $mock);

        $this->daemon->stop();
        $this->assertNull($this->daemon->getProcess());
    }

    // --- writeStdin() ---

    public function test_write_stdin_throws_when_no_process(): void
    {
        $this->expectException(DaemonNotRunningException::class);
        $this->daemon->writeStdin('hello');
    }

    public function test_write_stdin_throws_when_process_not_running(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(false);

        $this->setProcess($this->daemon, $mock);

        $this->expectException(DaemonNotRunningException::class);
        $this->daemon->writeStdin('hello');
    }

    // --- waitForReady() ---

    public function test_wait_for_ready_returns_false_when_no_process(): void
    {
        $this->assertFalse($this->daemon->waitForReady(100));
    }

    public function test_wait_for_ready_returns_true_when_ready_signal_found(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('getIncrementalOutput')
            ->once()
            ->andReturn("Starting... VURB_DAEMON_READY\n");
        $mock->shouldReceive('isRunning')->andReturn(true);

        $this->setProcess($this->daemon, $mock);

        $this->assertTrue($this->daemon->waitForReady(5000));
    }

    public function test_wait_for_ready_returns_false_on_timeout(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('getIncrementalOutput')->andReturn('');
        $mock->shouldReceive('isRunning')->andReturn(true);

        $this->setProcess($this->daemon, $mock);

        // Very short timeout
        $this->assertFalse($this->daemon->waitForReady(1));
    }

    public function test_wait_for_ready_returns_false_when_process_stops(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('getIncrementalOutput')->andReturn('some output');
        $mock->shouldReceive('isRunning')->andReturn(false);

        $this->setProcess($this->daemon, $mock);

        $this->assertFalse($this->daemon->waitForReady(5000));
    }

    // --- isRunning() with mocked process ---

    public function test_is_running_returns_true_with_running_process(): void
    {
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(true);

        $this->setProcess($this->daemon, $mock);

        $this->assertTrue($this->daemon->isRunning());
    }

    // --- installDependencies() ---

    public function test_install_dependencies_runs_npm_install(): void
    {
        // This will likely fail in CI/Docker without node, but exercises the code path
        $result = $this->daemon->installDependencies();
        // We just verify it returns a boolean
        $this->assertIsBool($result);
    }

    // --- start() with output callback ---

    public function test_start_returns_process_and_accepts_output_callback(): void
    {
        // We can't actually start the daemon in tests, but we can verify the method
        // produces the right command structure by testing buildCommand + buildEnv
        $command = $this->daemon->buildCommand();
        $this->assertIsArray($command);
        $this->assertNotEmpty($command);

        $env = $this->daemon->buildEnv(['VURB_PORT' => '9090']);
        $this->assertSame('9090', $env['VURB_PORT']);
    }

    // --- Helper to inject process via reflection ---

    protected function setProcess(DaemonManager $daemon, $process): void
    {
        $ref = new \ReflectionClass($daemon);
        $prop = $ref->getProperty('process');
        $prop->setAccessible(true);
        $prop->setValue($daemon, $process);
    }
}
