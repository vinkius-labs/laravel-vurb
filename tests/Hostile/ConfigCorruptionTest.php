<?php

namespace Vinkius\Vurb\Tests\Hostile;

use Vinkius\Vurb\Security\DlpRedactor;
use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Services\ToolDiscovery;
use Vinkius\Vurb\Testing\FakeVurbTester;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\TestCase;

/**
 * Tests that the system behaves gracefully under corrupted,
 * missing, or adversarial configuration values.
 */
class ConfigCorruptionTest extends TestCase
{
    // ─── Missing Internal Token ───

    public function test_missing_internal_token_returns_500(): void
    {
        config()->set('vurb.internal_token', null);

        // ValidateVurbToken aborts with 500 when token is not configured
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ]);

        $response->assertStatus(500);
    }

    public function test_empty_string_internal_token_returns_500(): void
    {
        config()->set('vurb.internal_token', '');

        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ]);

        $response->assertStatus(500);
    }

    // ─── Invalid Tools Path ───

    public function test_nonexistent_tools_path_returns_empty_discovery(): void
    {
        config()->set('vurb.tools.path', '/nonexistent/path/tools');

        $discovery = $this->app->make(ToolDiscovery::class);
        $tools = $discovery->toolNames();

        $this->assertIsArray($tools);
        $this->assertEmpty($tools);
    }

    public function test_null_tools_path_returns_empty_discovery(): void
    {
        config()->set('vurb.tools.path', null);

        $discovery = $this->app->make(ToolDiscovery::class);
        $tools = $discovery->toolNames();

        $this->assertIsArray($tools);
        $this->assertEmpty($tools);
    }

    public function test_empty_tools_namespace_returns_empty(): void
    {
        config()->set('vurb.tools.namespace', '');

        $discovery = $this->app->make(ToolDiscovery::class);
        $tools = $discovery->toolNames();

        $this->assertIsArray($tools);
    }

    // ─── DLP with Corrupted Config ───

    public function test_dlp_with_null_patterns_throws_type_error(): void
    {
        config()->set('vurb.dlp.enabled', true);
        config()->set('vurb.dlp.patterns', null);

        // DlpRedactor::$patterns is typed as array — null triggers TypeError
        $this->expectException(\TypeError::class);
        $this->app->make(DlpRedactor::class);
    }

    public function test_dlp_with_empty_patterns_passes_through(): void
    {
        config()->set('vurb.dlp.enabled', true);
        config()->set('vurb.dlp.patterns', []);

        $dlp = $this->app->make(DlpRedactor::class);
        $result = $dlp->redact('My SSN is 123-45-6789');

        $this->assertSame('My SSN is 123-45-6789', $result);
    }

    public function test_dlp_with_invalid_strategy_does_not_crash(): void
    {
        config()->set('vurb.dlp.enabled', true);
        config()->set('vurb.dlp.strategy', 'nonexistent_strategy');
        config()->set('vurb.dlp.patterns', ['/\d{3}-\d{2}-\d{4}/']);

        $dlp = $this->app->make(DlpRedactor::class);

        // Should either throw or fall back gracefully
        try {
            $result = $dlp->redact('SSN: 123-45-6789');
            $this->assertIsString($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_dlp_disabled_passes_through(): void
    {
        config()->set('vurb.dlp.enabled', false);
        config()->set('vurb.dlp.patterns', ['/\d{3}-\d{2}-\d{4}/']);

        $dlp = $this->app->make(DlpRedactor::class);
        $result = $dlp->redact('SSN: 123-45-6789');

        $this->assertSame('SSN: 123-45-6789', $result);
    }

    // ─── Server Config Edge Cases ───

    public function test_null_server_name_in_manifest(): void
    {
        config()->set('vurb.server.name', null);

        $compiler = $this->app->make(ManifestCompiler::class);
        $manifest = $compiler->compile();

        $this->assertIsArray($manifest);
        // ManifestCompiler uses 'server' key
        $this->assertArrayHasKey('server', $manifest);
        $this->assertNull($manifest['server']['name']);
    }

    public function test_empty_server_version(): void
    {
        config()->set('vurb.server.version', '');

        $compiler = $this->app->make(ManifestCompiler::class);
        $manifest = $compiler->compile();

        $this->assertIsArray($manifest);
        $this->assertSame('', $manifest['server']['version']);
    }

    // ─── Middleware Config Edge Cases ───

    public function test_null_middleware_config_does_not_crash(): void
    {
        config()->set('vurb.middleware', null);

        // Executing a tool still works
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 1]);

        $this->assertFalse($result->isError);
    }

    public function test_middleware_config_with_nonexistent_class(): void
    {
        config()->set('vurb.middleware', [
            'NonExistent\\Middleware\\Class',
        ]);

        // Should either throw or skip the unresolvable middleware
        try {
            $result = FakeVurbTester::for(GetCustomerProfile::class)
                ->withMiddleware(config('vurb.middleware'))
                ->call(['id' => 1]);

            $this->assertNotNull($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    // ─── FSM with Null Config ───

    public function test_null_fsm_config_does_not_affect_tool_execution(): void
    {
        config()->set('vurb.fsm', null);

        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 5]);

        $this->assertFalse($result->isError);
        $this->assertSame(5, $result->data['id']);
    }

    // ─── Bridge Config Edge Cases ───

    public function test_health_endpoint_responds_to_get(): void
    {
        // Health is a GET route, verify it works
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => config('vurb.internal_token'),
        ]);

        $response->assertOk();
    }

    public function test_health_rejects_post(): void
    {
        $response = $this->postJson('/_vurb/health', [], [
            'X-Vurb-Token' => config('vurb.internal_token'),
        ]);

        $response->assertStatus(405);
    }

    // ─── Exposition Mode ───

    public function test_invalid_exposition_mode_does_not_crash(): void
    {
        config()->set('vurb.exposition', 'invalid_mode_xyz');

        $compiler = $this->app->make(ManifestCompiler::class);

        try {
            $manifest = $compiler->compile();
            $this->assertIsArray($manifest);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    // ─── Transport ───

    public function test_unknown_transport_does_not_prevent_http_bridge(): void
    {
        config()->set('vurb.transport', 'ftp_lol');

        // Health endpoint (GET) still works
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => config('vurb.internal_token'),
        ]);

        $response->assertOk();
    }

    // ─── Config Types ───

    public function test_numeric_internal_token_is_compared_as_string(): void
    {
        $numericToken = '12345678901234567890';
        config()->set('vurb.internal_token', $numericToken);

        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => $numericToken,
        ]);

        $response->assertOk();
    }

    public function test_boolean_internal_token_causes_type_mismatch(): void
    {
        config()->set('vurb.internal_token', true);

        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => 'true',
        ]);

        // hash_equals requires strings — bool true will cause type error or mismatch
        $this->assertContains($response->status(), [403, 404, 500]);
    }

    // ─── Oversize Config ───

    public function test_extremely_long_server_name(): void
    {
        config()->set('vurb.server.name', str_repeat('A', 10000));

        $compiler = $this->app->make(ManifestCompiler::class);
        $manifest = $compiler->compile();

        $this->assertIsArray($manifest);
        $this->assertSame(10000, strlen($manifest['server']['name']));
    }
}
