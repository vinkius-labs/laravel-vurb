<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\File;
use Mockery;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure clean Vurb dirs don't persist between tests
        $dirs = [
            app_path('Vurb/Tools'),
            app_path('Vurb/Middleware'),
            app_path('Vurb/Presenters'),
        ];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    protected function tearDown(): void
    {
        // Cleanup created dirs
        $base = app_path('Vurb');
        if (is_dir($base)) {
            $this->removeDir($base);
        }

        // Cleanup .env if we created one
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            unlink($envPath);
        }

        Mockery::close();
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // --- handle() runs all steps ---

    public function test_handle_runs_all_steps_successfully(): void
    {
        // Mock DaemonManager to avoid real Node check
        $mock = Mockery::mock(DaemonManager::class);
        $mock->shouldReceive('isNodeAvailable')->andReturn(true);
        $mock->shouldReceive('getNodeVersion')->andReturn('v20.0.0');
        $mock->shouldReceive('installDependencies')->andReturn(true);
        $this->app->instance(DaemonManager::class, $mock);

        // Create .env so token generation works
        file_put_contents(base_path('.env'), "APP_KEY=base64:test\n");

        $this->artisan('vurb:install')
            ->assertSuccessful();

        // Verify directories were created
        $this->assertDirectoryExists(app_path('Vurb/Tools'));
        $this->assertDirectoryExists(app_path('Vurb/Middleware'));
        $this->assertDirectoryExists(app_path('Vurb/Presenters'));

        // Verify token was written to .env
        $env = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('VURB_INTERNAL_TOKEN=', $env);
    }

    // --- publishConfig with --force ---

    public function test_publish_config_with_force_flag(): void
    {
        $mock = Mockery::mock(DaemonManager::class);
        $mock->shouldReceive('isNodeAvailable')->andReturn(false);
        $this->app->instance(DaemonManager::class, $mock);

        file_put_contents(base_path('.env'), "APP_KEY=base64:test\n");

        $this->artisan('vurb:install', ['--force' => true])
            ->assertSuccessful();
    }

    // --- installNpmDependencies when Node not available ---

    public function test_install_npm_warns_when_node_not_available(): void
    {
        $mock = Mockery::mock(DaemonManager::class);
        $mock->shouldReceive('isNodeAvailable')->andReturn(false);
        $this->app->instance(DaemonManager::class, $mock);

        file_put_contents(base_path('.env'), "APP_KEY=base64:test\n");

        $this->artisan('vurb:install')
            ->assertSuccessful();
    }

    // --- generateToken when .env doesn't exist ---

    public function test_generate_token_warns_when_env_missing(): void
    {
        $mock = Mockery::mock(DaemonManager::class);
        $mock->shouldReceive('isNodeAvailable')->andReturn(false);
        $this->app->instance(DaemonManager::class, $mock);

        // Don't create .env
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            unlink($envPath);
        }

        $this->artisan('vurb:install')
            ->assertSuccessful();
    }

    // --- generateToken skips when token already set without --force ---

    public function test_generate_token_skips_when_already_set_without_force(): void
    {
        $mock = Mockery::mock(DaemonManager::class);
        $mock->shouldReceive('isNodeAvailable')->andReturn(false);
        $this->app->instance(DaemonManager::class, $mock);

        file_put_contents(base_path('.env'), "APP_KEY=base64:test\nVURB_INTERNAL_TOKEN=existing_token\n");

        $this->artisan('vurb:install')
            ->assertSuccessful();

        // Token should NOT have been changed
        $env = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('VURB_INTERNAL_TOKEN=existing_token', $env);
    }

    // --- generateToken regenerates when --force used ---

    public function test_generate_token_regenerates_with_force_flag(): void
    {
        $mock = Mockery::mock(DaemonManager::class);
        $mock->shouldReceive('isNodeAvailable')->andReturn(false);
        $this->app->instance(DaemonManager::class, $mock);

        file_put_contents(base_path('.env'), "APP_KEY=base64:test\nVURB_INTERNAL_TOKEN=old_token\n");

        $this->artisan('vurb:install', ['--force' => true])
            ->assertSuccessful();

        // Token should have been regenerated
        $env = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('VURB_INTERNAL_TOKEN=vurb_local_', $env);
        $this->assertStringNotContainsString('old_token', $env);
    }
}
