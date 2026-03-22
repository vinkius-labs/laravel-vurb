<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Tests\TestCase;

class DaemonManagerTest extends TestCase
{
    protected DaemonManager $daemon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->daemon = $this->app->make(DaemonManager::class);
    }

    // ─── Path Resolution ───

    public function test_get_daemon_script_path_returns_bridge_ts(): void
    {
        $path = $this->daemon->getDaemonScriptPath();
        $this->assertStringEndsWith('bin/daemon/src/bridge.ts', str_replace('\\', '/', $path));
    }

    public function test_get_daemon_package_path_returns_bin_daemon(): void
    {
        $path = $this->daemon->getDaemonPackagePath();
        $this->assertStringEndsWith('bin/daemon', str_replace('\\', '/', $path));
    }

    // ─── Npx / Node Resolution ───

    public function test_resolve_npx_path_default(): void
    {
        config()->set('vurb.daemon.npx_path', null);
        $npx = $this->daemon->resolveNpxPath();
        $expected = PHP_OS_FAMILY === 'Windows' ? 'npx.cmd' : 'npx';
        $this->assertSame($expected, $npx);
    }

    public function test_resolve_npx_path_custom(): void
    {
        config()->set('vurb.daemon.npx_path', '/custom/npx');
        $npx = $this->daemon->resolveNpxPath();
        $this->assertSame('/custom/npx', $npx);
    }

    public function test_resolve_node_path_default(): void
    {
        config()->set('vurb.daemon.node_path', null);
        $node = $this->daemon->resolveNodePath();
        $expected = PHP_OS_FAMILY === 'Windows' ? 'node.exe' : 'node';
        $this->assertSame($expected, $node);
    }

    public function test_resolve_node_path_custom(): void
    {
        config()->set('vurb.daemon.node_path', '/custom/node');
        $node = $this->daemon->resolveNodePath();
        $this->assertSame('/custom/node', $node);
    }

    // ─── Build Env ───

    public function test_build_env_contains_required_keys(): void
    {
        $env = $this->daemon->buildEnv();
        $this->assertArrayHasKey('VURB_MANIFEST_PATH', $env);
        $this->assertArrayHasKey('VURB_INTERNAL_TOKEN', $env);
        $this->assertArrayHasKey('VURB_BRIDGE_URL', $env);
        $this->assertArrayHasKey('VURB_TRANSPORT', $env);
        $this->assertArrayHasKey('NODE_ENV', $env);
    }

    public function test_build_env_includes_port_when_configured(): void
    {
        config()->set('vurb.daemon.port', 3001);
        $env = $this->daemon->buildEnv();
        $this->assertSame('3001', $env['VURB_PORT']);
    }

    public function test_build_env_excludes_port_when_null(): void
    {
        config()->set('vurb.daemon.port', null);
        $env = $this->daemon->buildEnv();
        $this->assertArrayNotHasKey('VURB_PORT', $env);
    }

    public function test_build_env_overrides(): void
    {
        $env = $this->daemon->buildEnv(['CUSTOM_KEY' => 'value']);
        $this->assertSame('value', $env['CUSTOM_KEY']);
    }

    public function test_build_env_override_replaces_default(): void
    {
        $env = $this->daemon->buildEnv(['VURB_TRANSPORT' => 'sse']);
        $this->assertSame('sse', $env['VURB_TRANSPORT']);
    }

    // ─── Build Command ───

    public function test_build_command_uses_npx_tsx(): void
    {
        $cmd = $this->daemon->buildCommand();
        $this->assertCount(3, $cmd);
        $this->assertSame('tsx', $cmd[1]);
        $this->assertStringContains('bridge.ts', $cmd[2]);
    }

    // ─── Process State (not started) ───

    public function test_is_running_false_when_not_started(): void
    {
        $this->assertFalse($this->daemon->isRunning());
    }

    public function test_get_process_info_null_when_not_started(): void
    {
        $this->assertNull($this->daemon->getProcessInfo());
    }

    public function test_get_process_null_when_not_started(): void
    {
        $this->assertNull($this->daemon->getProcess());
    }

    public function test_stop_does_nothing_when_not_started(): void
    {
        // Should not throw
        $this->daemon->stop();
        $this->assertFalse($this->daemon->isRunning());
    }

    public function test_wait_for_ready_false_when_no_process(): void
    {
        $this->assertFalse($this->daemon->waitForReady(100));
    }

    public function test_write_stdin_throws_when_not_running(): void
    {
        $this->expectException(\Vinkius\Vurb\Exceptions\DaemonNotRunningException::class);
        $this->daemon->writeStdin('test');
    }

    // ─── Node Availability ───

    public function test_is_node_available_returns_bool(): void
    {
        $this->assertIsBool($this->daemon->isNodeAvailable());
    }

    public function test_get_node_version_returns_string_or_null(): void
    {
        $version = $this->daemon->getNodeVersion();
        if ($version !== null) {
            $this->assertMatchesRegularExpression('/^v?\d+/', $version);
        } else {
            $this->assertNull($version);
        }
    }

    /**
     * Helper for PHP < 8.1 compat — not PHPUnit built-in.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
