<?php

namespace Vinkius\Vurb\Tests\Unit;

use Mockery;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\HealthCheck;
use Vinkius\Vurb\Tests\TestCase;

class HealthCheckExtendedTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_status_returns_all_sections(): void
    {
        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isRunning')->andReturn(false);
        $daemon->shouldReceive('getProcessInfo')->andReturn(null);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(true);
        $daemon->shouldReceive('getNodeVersion')->andReturn('v20.0.0');
        $this->app->instance(DaemonManager::class, $daemon);

        $health = $this->app->make(HealthCheck::class);
        $status = $health->status();

        $this->assertArrayHasKey('daemon', $status);
        $this->assertArrayHasKey('node', $status);
        $this->assertArrayHasKey('bridge', $status);
        $this->assertArrayHasKey('tools', $status);
        $this->assertArrayHasKey('config', $status);

        $this->assertFalse($status['daemon']['running']);
        $this->assertNull($status['daemon']['process']);
        $this->assertTrue($status['node']['available']);
        $this->assertSame('v20.0.0', $status['node']['version']);
        $this->assertIsInt($status['tools']['count']);
        $this->assertIsArray($status['tools']['names']);
    }

    public function test_check_returns_false_when_daemon_not_running(): void
    {
        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isRunning')->andReturn(false);
        $this->app->instance(DaemonManager::class, $daemon);

        $health = $this->app->make(HealthCheck::class);
        $this->assertFalse($health->check());
    }

    public function test_is_bridge_reachable_returns_false_on_connection_error(): void
    {
        $this->app['config']->set('vurb.bridge.base_url', 'http://127.0.0.1:19999');

        $health = $this->app->make(HealthCheck::class);
        $this->assertFalse($health->isBridgeReachable());
    }

    public function test_status_reports_tool_count(): void
    {
        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isRunning')->andReturn(false);
        $daemon->shouldReceive('getProcessInfo')->andReturn(null);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(false);
        $daemon->shouldReceive('getNodeVersion')->andReturn(null);
        $this->app->instance(DaemonManager::class, $daemon);

        $health = $this->app->make(HealthCheck::class);
        $status = $health->status();

        // Should discover 18 fixture tools (11 + 5 Crm + 2 serialization)
        $this->assertSame(18, $status['tools']['count']);
        $this->assertContains('crm.get_lead', $status['tools']['names']);
    }

    public function test_status_config_section(): void
    {
        $this->app['config']->set('vurb.transport', 'sse');
        $this->app['config']->set('vurb.internal_token', 'secret');
        $this->app['config']->set('vurb.daemon.manifest_path', '/tmp/manifest.json');

        $daemon = Mockery::mock(DaemonManager::class);
        $daemon->shouldReceive('isRunning')->andReturn(false);
        $daemon->shouldReceive('getProcessInfo')->andReturn(null);
        $daemon->shouldReceive('isNodeAvailable')->andReturn(true);
        $daemon->shouldReceive('getNodeVersion')->andReturn('v18.0.0');
        $this->app->instance(DaemonManager::class, $daemon);

        $health = $this->app->make(HealthCheck::class);
        $status = $health->status();

        $this->assertSame('sse', $status['config']['transport']);
        $this->assertTrue($status['config']['token_set']);
        $this->assertSame('/tmp/manifest.json', $status['config']['manifest_path']);
    }
}
