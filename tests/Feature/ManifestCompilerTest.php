<?php

namespace Vinkius\Vurb\Tests\Feature;

use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Tests\TestCase;

class ManifestCompilerTest extends TestCase
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
        $app['config']->set('vurb.server.version', '1.0.0');
        $app['config']->set('vurb.server.description', 'Test server');
        $app['config']->set('vurb.bridge.base_url', 'http://localhost');
        $app['config']->set('vurb.bridge.prefix', '/_vurb');
        $app['config']->set('vurb.exposition', 'flat');
        $app['config']->set('vurb.state_sync.default', 'stale');
        $app['config']->set('vurb.state_sync.policies', []);
        $app['config']->set('vurb.fsm', null);
    }

    public function test_compile_returns_valid_manifest_structure(): void
    {
        $manifest = $this->compiler->compile();

        $this->assertArrayHasKey('version', $manifest);
        $this->assertSame('1.0', $manifest['version']);
        $this->assertArrayHasKey('server', $manifest);
        $this->assertArrayHasKey('bridge', $manifest);
        $this->assertArrayHasKey('tools', $manifest);
        $this->assertArrayHasKey('stateSync', $manifest);
        $this->assertArrayHasKey('presenters', $manifest);
        $this->assertArrayHasKey('skills', $manifest);
    }

    public function test_compile_server_section(): void
    {
        $manifest = $this->compiler->compile();

        $this->assertSame('test-server', $manifest['server']['name']);
        $this->assertSame('1.0.0', $manifest['server']['version']);
        $this->assertSame('Test server', $manifest['server']['description']);
    }

    public function test_compile_bridge_section(): void
    {
        $manifest = $this->compiler->compile();

        $this->assertSame('http://localhost', $manifest['bridge']['baseUrl']);
        $this->assertSame('/_vurb', $manifest['bridge']['prefix']);
        $this->assertNotEmpty($manifest['bridge']['token']);
    }

    public function test_compile_tools_are_grouped_by_namespace(): void
    {
        $manifest = $this->compiler->compile();
        $tools = $manifest['tools'];

        // Should be keyed by namespace string (Record<string, ManifestTool[]>)
        $this->assertArrayHasKey('customers', $tools);
        $this->assertArrayHasKey('orders', $tools);
        $this->assertArrayHasKey('products', $tools);
        $this->assertArrayHasKey('payments', $tools);
        $this->assertArrayHasKey('notifications', $tools);
    }

    public function test_compile_tool_actions_have_required_fields(): void
    {
        $manifest = $this->compiler->compile();

        foreach ($manifest['tools'] as $namespace => $tools) {
            $this->assertIsString($namespace);
            $this->assertIsArray($tools);

            foreach ($tools as $tool) {
                $this->assertArrayHasKey('name', $tool);
                $this->assertArrayHasKey('verb', $tool);
                $this->assertArrayHasKey('description', $tool);
                $this->assertArrayHasKey('inputSchema', $tool);
                $this->assertArrayHasKey('annotations', $tool);
                $this->assertArrayHasKey('middleware', $tool);
                // annotations must include verb for daemon compatibility
                $this->assertArrayHasKey('verb', $tool['annotations']);
            }
        }
    }

    public function test_compile_state_sync_section(): void
    {
        $manifest = $this->compiler->compile();

        $this->assertArrayHasKey('default', $manifest['stateSync']);
        $this->assertArrayHasKey('policies', $manifest['stateSync']);
        $this->assertSame('stale', $manifest['stateSync']['default']);
    }

    public function test_compile_with_state_sync_policies(): void
    {
        $this->app['config']->set('vurb.state_sync.policies', [
            'customers.*' => ['directive' => 'stale-after', 'ttl' => 120],
        ]);

        $compiler = $this->app->make(ManifestCompiler::class);
        $manifest = $compiler->compile();

        $policies = $manifest['stateSync']['policies'];
        $this->assertCount(1, $policies);
        $this->assertArrayHasKey('customers.*', $policies);
        $this->assertSame('stale-after', $policies['customers.*']['directive']);
    }

    public function test_compile_and_write_creates_file(): void
    {
        $path = sys_get_temp_dir() . '/vurb-test-manifest-' . uniqid() . '.json';
        $this->app['config']->set('vurb.daemon.manifest_path', $path);

        try {
            $resultPath = $this->compiler->compileAndWrite();

            $this->assertSame($path, $resultPath);
            $this->assertFileExists($path);

            $contents = json_decode(file_get_contents($path), true);
            $this->assertSame('1.0', $contents['version']);
            $this->assertArrayHasKey('tools', $contents);
        } finally {
            @unlink($path);
        }
    }
}
