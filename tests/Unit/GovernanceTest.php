<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Governance\ContractCompiler;
use Vinkius\Vurb\Governance\DynamicManifest;
use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Tests\TestCase;

class GovernanceTest extends TestCase
{
    // ═══ DynamicManifest ═══

    public function test_resolve_returns_full_manifest_when_introspection_disabled(): void
    {
        config()->set('vurb.introspection.enabled', false);

        $manifest = $this->app->make(DynamicManifest::class);
        $result = $manifest->resolve();

        $this->assertArrayHasKey('tools', $result);
        $this->assertArrayHasKey('server', $result);
    }

    public function test_resolve_applies_filter_when_introspection_enabled(): void
    {
        config()->set('vurb.introspection.enabled', true);
        config()->set('vurb.introspection.filter', function (array $manifest, array $context) {
            // Strip all tools
            $manifest['tools'] = [];
            return $manifest;
        });

        $dm = $this->app->make(DynamicManifest::class);
        $result = $dm->resolve(['role' => 'guest']);

        $this->assertEmpty($result['tools']);
    }

    public function test_resolve_returns_full_manifest_when_filter_not_callable(): void
    {
        config()->set('vurb.introspection.enabled', true);
        config()->set('vurb.introspection.filter', 'not-callable');

        $dm = $this->app->make(DynamicManifest::class);
        $result = $dm->resolve();

        // Should return unfiltered
        $this->assertNotEmpty($result['tools']);
    }

    public function test_for_user_passes_user_context(): void
    {
        $captured = null;

        config()->set('vurb.introspection.enabled', true);
        config()->set('vurb.introspection.filter', function (array $manifest, array $context) use (&$captured) {
            $captured = $context;
            return $manifest;
        });

        $user = new class {
            public function getVurbRole(): string { return 'admin'; }
        };

        $dm = $this->app->make(DynamicManifest::class);
        $dm->forUser($user);

        $this->assertSame('admin', $captured['role']);
        $this->assertSame($user, $captured['user']);
    }

    public function test_visible_tools_returns_tool_names(): void
    {
        config()->set('vurb.introspection.enabled', false);

        $dm = $this->app->make(DynamicManifest::class);
        $names = $dm->visibleTools();

        $this->assertIsArray($names);
        $this->assertNotEmpty($names);
        // GetCustomerProfile is one of our fixture tools
        $this->assertContains('customers.get_profile', $names);
    }

    public function test_visible_tools_respects_filter(): void
    {
        config()->set('vurb.introspection.enabled', true);
        config()->set('vurb.introspection.filter', function (array $manifest) {
            $manifest['tools'] = [];
            return $manifest;
        });

        $dm = $this->app->make(DynamicManifest::class);
        $names = $dm->visibleTools();

        $this->assertEmpty($names);
    }

    // ═══ ContractCompiler ═══

    public function test_compile_contracts_returns_contracts(): void
    {
        $cc = $this->app->make(ContractCompiler::class);
        $contracts = $cc->compileContracts();

        $this->assertIsArray($contracts);
        // Should have contracts for our fixture tools
        $this->assertNotEmpty($contracts);
    }

    public function test_contract_has_expected_keys(): void
    {
        $cc = $this->app->make(ContractCompiler::class);
        $contracts = $cc->compileContracts();

        $first = array_values($contracts)[0];

        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('verb', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('inputSchema', $first);
        $this->assertArrayHasKey('digest', $first);
        $this->assertNotEmpty($first['digest']);
    }

    public function test_get_contract_existing(): void
    {
        $cc = $this->app->make(ContractCompiler::class);
        $contracts = $cc->compileContracts();
        $firstKey = array_key_first($contracts);

        $contract = $cc->getContract($firstKey);
        $this->assertNotNull($contract);
        $this->assertSame($firstKey, $contract['name']);
    }

    public function test_get_contract_nonexistent(): void
    {
        $cc = $this->app->make(ContractCompiler::class);
        $this->assertNull($cc->getContract('nonexistent.tool'));
    }

    public function test_contract_digest_is_sha256(): void
    {
        $cc = $this->app->make(ContractCompiler::class);
        $contracts = $cc->compileContracts();
        $first = array_values($contracts)[0];

        // sha256 is 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['digest']);
    }
}
