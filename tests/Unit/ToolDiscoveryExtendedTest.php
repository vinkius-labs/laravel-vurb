<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Services\ToolDiscovery;
use Vinkius\Vurb\Tests\TestCase;

class ToolDiscoveryExtendedTest extends TestCase
{
    protected ToolDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = $this->app->make(ToolDiscovery::class);
    }

    public function test_find_tool_returns_entry_for_existing_tool(): void
    {
        $entry = $this->discovery->findTool('customers.get_profile');

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('tool', $entry);
        $this->assertArrayHasKey('class', $entry);
        $this->assertArrayHasKey('middleware', $entry);
        $this->assertArrayHasKey('tags', $entry);
    }

    public function test_find_tool_returns_null_for_nonexistent(): void
    {
        $this->assertNull($this->discovery->findTool('nonexistent.tool'));
    }

    public function test_clear_cache_resets_discovered_tools(): void
    {
        $tools1 = $this->discovery->discover();
        $this->assertNotEmpty($tools1);

        $this->discovery->clearCache();

        // After clear, discovered is null, next call re-scans
        $tools2 = $this->discovery->discover();
        $this->assertNotEmpty($tools2);
        // Same count but potentially new instances
        $this->assertCount(count($tools1), $tools2);
    }

    public function test_tool_names_returns_array_of_strings(): void
    {
        $names = $this->discovery->toolNames();

        $this->assertIsArray($names);
        $this->assertNotEmpty($names);
        foreach ($names as $name) {
            $this->assertIsString($name);
        }
    }

    public function test_tool_names_contains_expected_tools(): void
    {
        $names = $this->discovery->toolNames();

        $this->assertContains('customers.get_profile', $names);
        $this->assertContains('customers.update', $names);
        $this->assertContains('payments.process', $names);
        $this->assertContains('notifications.send', $names);
        $this->assertContains('products.search', $names);
        $this->assertContains('orders.list', $names);
    }

    public function test_failing_tool_name_without_verb_prefix(): void
    {
        // FailingTool has no recognized verb prefix -> Str::snake('FailingTool') = 'failing_tool'
        $names = $this->discovery->toolNames();
        $this->assertContains('failing_tool', $names);

        $entry = $this->discovery->findTool('failing_tool');
        $this->assertNotNull($entry);
        $this->assertInstanceOf(
            \Vinkius\Vurb\Tests\Fixtures\Tools\FailingTool::class,
            $entry['tool']
        );
    }

    public function test_echo_tool_name_without_verb_prefix(): void
    {
        $names = $this->discovery->toolNames();
        $this->assertContains('echo_tool', $names);
    }

    public function test_null_returning_tool_name(): void
    {
        $names = $this->discovery->toolNames();
        $this->assertContains('null_returning_tool', $names);
    }

    public function test_falsy_return_tool_name(): void
    {
        $names = $this->discovery->toolNames();
        $this->assertContains('falsy_return_tool', $names);
    }

    public function test_discover_returns_empty_for_invalid_path(): void
    {
        $this->app['config']->set('vurb.tools.path', '/nonexistent/path');

        $discovery = $this->app->make(ToolDiscovery::class);
        $discovery->clearCache();

        $tools = $discovery->discover();
        $this->assertEmpty($tools);
    }

    public function test_middleware_includes_global_config(): void
    {
        $this->app['config']->set('vurb.middleware', ['App\\SomeGlobalMiddleware']);

        $discovery = new ToolDiscovery();
        $tools = $discovery->discover();

        foreach ($tools as $entry) {
            $this->assertContains('App\\SomeGlobalMiddleware', $entry['middleware']);
        }
    }

    // ═══ Router/CRM subdirectory scanning ═══

    public function test_discover_finds_crm_tools_via_router(): void
    {
        $names = $this->discovery->toolNames();

        $this->assertContains('crm.get_lead', $names);
        $this->assertContains('crm.update_lead', $names);
    }

    public function test_crm_tool_has_router_prefix_in_name(): void
    {
        $entry = $this->discovery->findTool('crm.get_lead');

        $this->assertNotNull($entry);
        $this->assertNotNull($entry['router']);
        $this->assertSame('crm', $entry['router']->prefix);
    }

    public function test_crm_tool_inherits_router_tags(): void
    {
        $entry = $this->discovery->findTool('crm.get_lead');

        $this->assertNotNull($entry);
        $this->assertContains('crm-router', $entry['tags']);
    }

    public function test_crm_router_description(): void
    {
        $entry = $this->discovery->findTool('crm.get_lead');

        $this->assertNotNull($entry);
        $this->assertSame('CRM tool group', $entry['router']->description);
    }

    public function test_crm_update_lead_also_discovered_with_router_prefix(): void
    {
        $entry = $this->discovery->findTool('crm.update_lead');

        $this->assertNotNull($entry);
        $this->assertNotNull($entry['router']);
        $this->assertSame('crm', $entry['router']->prefix);
        // UpdateLead has no tool-level tags, so only router tags
        $this->assertContains('crm-router', $entry['tags']);
    }

    public function test_total_tool_count_includes_crm_tools(): void
    {
        $tools = $this->discovery->discover();
        // 11 original + 2 Crm + 2 serialization = 15
        $this->assertCount(18, $tools);
    }
}
