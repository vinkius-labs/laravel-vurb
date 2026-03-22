<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Tests\TestCase;

class ManifestCompilerExtendedTest extends TestCase
{
    protected ManifestCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = $this->app->make(ManifestCompiler::class);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('vurb.server.name', 'test-server');
        $app['config']->set('vurb.server.version', '2.0.0');
        $app['config']->set('vurb.server.description', 'Extended test');
        $app['config']->set('vurb.bridge.base_url', 'http://localhost:8080');
        $app['config']->set('vurb.bridge.prefix', '/_vurb');
        $app['config']->set('vurb.exposition', 'grouped');
        $app['config']->set('vurb.state_sync.default', 'stale');
        $app['config']->set('vurb.state_sync.policies', []);
        $app['config']->set('vurb.fsm', null);
    }

    // ═══ CRM tools grouped under 'crm' namespace ═══

    public function test_compile_includes_crm_namespace(): void
    {
        $manifest = $this->compiler->compile();

        $this->assertArrayHasKey('crm', $manifest['tools']);
    }

    public function test_crm_group_has_five_tools(): void
    {
        $manifest = $this->compiler->compile();

        $crmTools = $manifest['tools']['crm'];
        $this->assertCount(5, $crmTools);

        // name is the full dotted name (e.g., 'crm.get_lead')
        $toolNames = array_column($crmTools, 'name');
        $this->assertContains('crm.get_lead', $toolNames);
        $this->assertContains('crm.update_lead', $toolNames);
        $this->assertContains('crm.list_leads', $toolNames);
        $this->assertContains('crm.search_leads', $toolNames);
        $this->assertContains('crm.get_missing_lead', $toolNames);
    }

    public function test_crm_tools_have_router_tags(): void
    {
        $manifest = $this->compiler->compile();

        $crmTools = $manifest['tools']['crm'];

        foreach ($crmTools as $tool) {
            $this->assertContains('crm-router', $tool['tags']);
        }
    }

    // ═══ State sync policies with invalidates ═══

    public function test_state_sync_policies_with_invalidates(): void
    {
        $this->app['config']->set('vurb.state_sync.policies', [
            'orders.*' => [
                'directive' => 'invalidate-on',
                'invalidates' => ['customers.*'],
            ],
        ]);

        $compiler = $this->app->make(ManifestCompiler::class);
        $manifest = $compiler->compile();

        $policies = $manifest['stateSync']['policies'];
        $this->assertCount(1, $policies);
        $this->assertArrayHasKey('orders.*', $policies);
        $this->assertSame('invalidate-on', $policies['orders.*']['directive']);
        $this->assertSame(['customers.*'], $policies['orders.*']['invalidates']);
    }

    // ═══ FSM config passthrough ═══

    public function test_manifest_fsm_section_null_when_disabled(): void
    {
        $manifest = $this->compiler->compile();
        $this->assertNull($manifest['fsm']);
    }

    public function test_manifest_fsm_section_present_when_configured(): void
    {
        $this->app['config']->set('vurb.fsm', [
            'id' => 'order_flow',
            'initial' => 'idle',
            'states' => ['idle' => ['on' => ['START' => 'active']]],
        ]);

        $compiler = $this->app->make(ManifestCompiler::class);
        $manifest = $compiler->compile();

        $this->assertNotNull($manifest['fsm']);
        $this->assertSame('order_flow', $manifest['fsm']['id']);
    }

    // ═══ Tools without dots go into 'default' namespace ═══

    public function test_tools_without_dot_grouped_under_default(): void
    {
        $manifest = $this->compiler->compile();

        // echo_tool, failing_tool, null_returning_tool, falsy_return_tool don't have dots
        $this->assertArrayHasKey('default', $manifest['tools']);
    }

    // ═══ Exposition config passthrough ═══

    public function test_manifest_tool_exposition(): void
    {
        $manifest = $this->compiler->compile();
        $this->assertSame('grouped', $manifest['toolExposition']);
    }
}
