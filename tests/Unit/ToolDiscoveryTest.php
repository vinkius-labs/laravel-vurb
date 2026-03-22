<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Services\ToolDiscovery;
use Vinkius\Vurb\Tests\TestCase;

class ToolDiscoveryTest extends TestCase
{
    protected ToolDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = $this->app->make(ToolDiscovery::class);
    }

    public function test_discover_finds_all_fixture_tools(): void
    {
        $tools = $this->discovery->discover();

        $this->assertNotEmpty($tools);

        // Our fixture tools (original 7 + 4 hostile + 2 Crm + 2 serialization)
        $this->assertCount(18, $tools);
    }

    public function test_discover_returns_tool_instances(): void
    {
        $tools = $this->discovery->discover();

        foreach ($tools as $name => $entry) {
            $this->assertArrayHasKey('tool', $entry);
            $this->assertArrayHasKey('class', $entry);
            $this->assertArrayHasKey('middleware', $entry);
            $this->assertArrayHasKey('tags', $entry);
            $this->assertInstanceOf(\Vinkius\Vurb\Tools\VurbTool::class, $entry['tool']);
        }
    }

    public function test_find_tool_by_name(): void
    {
        $entry = $this->discovery->findTool('customers.get_profile');

        $this->assertNotNull($entry);
        $this->assertInstanceOf(
            \Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile::class,
            $entry['tool']
        );
    }

    public function test_find_tool_returns_null_for_unknown(): void
    {
        $entry = $this->discovery->findTool('does.not_exist');

        $this->assertNull($entry);
    }

    public function test_tool_names_returns_all_keys(): void
    {
        $names = $this->discovery->toolNames();

        $this->assertContains('customers.get_profile', $names);
        $this->assertContains('customers.update', $names);
        $this->assertContains('notifications.send', $names);
        $this->assertContains('payments.process', $names);
        $this->assertContains('products.search', $names);
        $this->assertContains('orders.list', $names);
    }

    public function test_discover_caches_results(): void
    {
        $first = $this->discovery->discover();
        $second = $this->discovery->discover();

        $this->assertSame($first, $second);
    }

    public function test_clear_cache_resets_discovery(): void
    {
        $this->discovery->discover();
        $this->discovery->clearCache();

        // Re-calling should re-scan
        $tools = $this->discovery->discover();
        $this->assertNotEmpty($tools);
    }

    public function test_tags_are_discovered_from_attributes(): void
    {
        $entry = $this->discovery->findTool('customers.get_profile');

        $this->assertContains('crm', $entry['tags']);
        $this->assertContains('public', $entry['tags']);
    }

    public function test_global_middleware_is_included(): void
    {
        // Set global middleware in config
        $this->app['config']->set('vurb.middleware', ['SomeMiddleware']);

        // Clear cache and re-discover
        $this->discovery->clearCache();
        $entry = $this->discovery->findTool('customers.get_profile');

        $this->assertContains('SomeMiddleware', $entry['middleware']);
    }

    public function test_returns_empty_for_invalid_path(): void
    {
        $this->app['config']->set('vurb.tools.path', '/nonexistent/path');
        $this->discovery->clearCache();

        $tools = $this->discovery->discover();

        $this->assertEmpty($tools);
    }
}
