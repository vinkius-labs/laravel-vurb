<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Facades\Vurb;
use Vinkius\Vurb\Models\ModelRegistry;
use Vinkius\Vurb\Presenters\PresenterRegistry;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\HealthCheck;
use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Services\ToolDiscovery;
use Vinkius\Vurb\Tests\TestCase;
use Vinkius\Vurb\VurbManager;
use Vinkius\Vurb\VurbServiceProvider;

class ServiceProviderTest extends TestCase
{
    // ─── Singleton Bindings ───

    public function test_registers_reflection_engine_as_singleton(): void
    {
        $a = $this->app->make(\Vinkius\Vurb\Services\ReflectionEngine::class);
        $b = $this->app->make(\Vinkius\Vurb\Services\ReflectionEngine::class);
        $this->assertSame($a, $b);
    }

    public function test_registers_tool_discovery_as_singleton(): void
    {
        $a = $this->app->make(ToolDiscovery::class);
        $b = $this->app->make(ToolDiscovery::class);
        $this->assertSame($a, $b);
    }

    public function test_registers_manifest_compiler_as_singleton(): void
    {
        $a = $this->app->make(ManifestCompiler::class);
        $b = $this->app->make(ManifestCompiler::class);
        $this->assertSame($a, $b);
    }

    public function test_registers_presenter_registry_as_singleton(): void
    {
        $a = $this->app->make(PresenterRegistry::class);
        $b = $this->app->make(PresenterRegistry::class);
        $this->assertSame($a, $b);
    }

    public function test_registers_dlp_redactor_as_singleton(): void
    {
        $a = $this->app->make(\Vinkius\Vurb\Security\DlpRedactor::class);
        $b = $this->app->make(\Vinkius\Vurb\Security\DlpRedactor::class);
        $this->assertSame($a, $b);
    }

    public function test_registers_daemon_manager_as_singleton(): void
    {
        $a = $this->app->make(DaemonManager::class);
        $b = $this->app->make(DaemonManager::class);
        $this->assertSame($a, $b);
    }

    public function test_registers_health_check_as_singleton(): void
    {
        $a = $this->app->make(HealthCheck::class);
        $b = $this->app->make(HealthCheck::class);
        $this->assertSame($a, $b);
    }

    public function test_registers_vurb_manager_as_singleton(): void
    {
        $a = $this->app->make(VurbManager::class);
        $b = $this->app->make(VurbManager::class);
        $this->assertSame($a, $b);
    }

    // ─── Config Merge ───

    public function test_merges_default_config(): void
    {
        $this->assertNotNull(config('vurb.server.name'));
        $this->assertNotNull(config('vurb.bridge.prefix'));
        $this->assertIsArray(config('vurb.middleware'));
    }

    // ─── Routes ───

    public function test_registers_vurb_routes(): void
    {
        $routes = $this->app['router']->getRoutes();
        $routeNames = collect($routes->getRoutes())->map(fn ($r) => $r->uri());
        $this->assertTrue($routeNames->contains('_vurb/health'));
        $this->assertTrue($routeNames->contains(fn ($v) => str_contains($v, '_vurb/execute')));
    }

    // ─── VurbManager ───

    public function test_manager_discover_returns_array(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertIsArray($manager->discover());
    }

    public function test_manager_compile_manifest_returns_array(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertIsArray($manager->compileManifest());
    }

    public function test_manager_accessor_discovery(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertInstanceOf(ToolDiscovery::class, $manager->discovery());
    }

    public function test_manager_accessor_compiler(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertInstanceOf(ManifestCompiler::class, $manager->compiler());
    }

    public function test_manager_accessor_daemon(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertInstanceOf(DaemonManager::class, $manager->daemon());
    }

    public function test_manager_accessor_health(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertInstanceOf(HealthCheck::class, $manager->health());
    }

    public function test_manager_accessor_presenters(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertInstanceOf(PresenterRegistry::class, $manager->presenters());
    }

    public function test_manager_accessor_models(): void
    {
        $manager = $this->app->make(VurbManager::class);
        $this->assertInstanceOf(ModelRegistry::class, $manager->models());
    }

    // ─── Facade ───

    public function test_facade_resolves_to_manager(): void
    {
        $this->assertInstanceOf(VurbManager::class, Vurb::getFacadeRoot());
    }

    public function test_facade_discover(): void
    {
        $this->assertIsArray(Vurb::discover());
    }

    public function test_facade_compile_manifest(): void
    {
        $this->assertIsArray(Vurb::compileManifest());
    }

    public function test_facade_accessor_methods(): void
    {
        $this->assertInstanceOf(\Vinkius\Vurb\Services\ToolDiscovery::class, Vurb::discovery());
        $this->assertInstanceOf(\Vinkius\Vurb\Services\ManifestCompiler::class, Vurb::compiler());
        $this->assertInstanceOf(\Vinkius\Vurb\Services\DaemonManager::class, Vurb::daemon());
        $this->assertInstanceOf(\Vinkius\Vurb\Services\HealthCheck::class, Vurb::health());
        $this->assertInstanceOf(\Vinkius\Vurb\Presenters\PresenterRegistry::class, Vurb::presenters());
        $this->assertInstanceOf(\Vinkius\Vurb\Models\ModelRegistry::class, Vurb::models());
        $this->assertIsBool(Vurb::isHealthy());
    }
}
