<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\HealthCheck;
use Vinkius\Vurb\Tests\TestCase;

class HealthCheckTest extends TestCase
{
    protected HealthCheck $health;

    protected function setUp(): void
    {
        parent::setUp();
        $this->health = $this->app->make(HealthCheck::class);
    }

    public function test_check_returns_false_when_daemon_not_running(): void
    {
        $this->assertFalse($this->health->check());
    }

    public function test_is_daemon_running_false_when_not_started(): void
    {
        $this->assertFalse($this->health->isDaemonRunning());
    }

    public function test_is_bridge_reachable_false_for_invalid_url(): void
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $this->assertFalse($this->health->isBridgeReachable());
    }

    public function test_is_bridge_reachable_true_on_200(): void
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->assertTrue($this->health->isBridgeReachable());
    }

    public function test_status_returns_all_keys(): void
    {
        $status = $this->health->status();

        $this->assertArrayHasKey('daemon', $status);
        $this->assertArrayHasKey('node', $status);
        $this->assertArrayHasKey('bridge', $status);
        $this->assertArrayHasKey('tools', $status);
        $this->assertArrayHasKey('config', $status);
    }

    public function test_status_daemon_section(): void
    {
        $status = $this->health->status();
        $this->assertFalse($status['daemon']['running']);
        $this->assertNull($status['daemon']['process']);
    }

    public function test_status_tools_section_counts(): void
    {
        $status = $this->health->status();
        $this->assertIsInt($status['tools']['count']);
        $this->assertIsArray($status['tools']['names']);
        $this->assertGreaterThan(0, $status['tools']['count']);
    }

    public function test_status_config_section(): void
    {
        $status = $this->health->status();
        $this->assertTrue($status['config']['token_set']);
        $this->assertNotNull($status['config']['manifest_path']);
    }

    public function test_is_bridge_reachable_catches_exceptions(): void
    {
        Http::fake(fn () => throw new \Exception('Connection refused'));

        $this->assertFalse($this->health->isBridgeReachable());
    }
}
