<?php

namespace Vinkius\Vurb\Tests\Feature;

use Illuminate\Support\Facades\File;
use Vinkius\Vurb\Tests\TestCase;

class ConsoleCommandTest extends TestCase
{
    // ═══ vurb:manifest ═══

    public function test_vurb_manifest_displays_output(): void
    {
        $this->artisan('vurb:manifest')
            ->assertExitCode(0);
    }

    public function test_vurb_manifest_json_flag(): void
    {
        $this->artisan('vurb:manifest', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_vurb_manifest_write_flag(): void
    {
        $path = sys_get_temp_dir() . '/vurb_test_manifest_' . uniqid() . '.json';
        config()->set('vurb.manifest.path', $path);

        $this->artisan('vurb:manifest', ['--write' => true])
            ->assertExitCode(0);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_vurb_manifest_write_and_json_flags(): void
    {
        $path = sys_get_temp_dir() . '/vurb_test_manifest_' . uniqid() . '.json';
        config()->set('vurb.manifest.path', $path);

        $this->artisan('vurb:manifest', ['--write' => true, '--json' => true])
            ->assertExitCode(0);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    // ═══ vurb:lock ═══

    public function test_vurb_lock_generates_lockfile(): void
    {
        $path = sys_get_temp_dir() . '/vurb_test_' . uniqid() . '.lock';

        $this->artisan('vurb:lock', ['--path' => $path])
            ->assertExitCode(0);

        $this->assertFileExists($path);
        $content = json_decode(file_get_contents($path), true);
        $this->assertArrayHasKey('version', $content);
        $this->assertArrayHasKey('serverDigest', $content);

        unlink($path);
    }

    public function test_vurb_lock_check_fails_when_no_lockfile(): void
    {
        $this->artisan('vurb:lock', ['--check' => true, '--path' => '/tmp/nonexistent_vurb.lock'])
            ->assertExitCode(1);
    }

    public function test_vurb_lock_check_passes_when_up_to_date(): void
    {
        $path = sys_get_temp_dir() . '/vurb_test_' . uniqid() . '.lock';

        // Generate it first
        $this->artisan('vurb:lock', ['--path' => $path]);

        // Now check
        $this->artisan('vurb:lock', ['--check' => true, '--path' => $path])
            ->assertExitCode(0);

        unlink($path);
    }

    public function test_vurb_lock_check_fails_when_lockfile_is_stale(): void
    {
        $path = sys_get_temp_dir() . '/vurb_test_' . uniqid() . '.lock';

        // Write a deliberately stale lockfile
        file_put_contents($path, json_encode(['version' => '0.0.0', 'serverDigest' => 'stale']));

        $this->artisan('vurb:lock', ['--check' => true, '--path' => $path])
            ->assertExitCode(1);

        unlink($path);
    }

    // ═══ vurb:health ═══

    public function test_vurb_health_command_runs(): void
    {
        // Daemon isn't running in tests, so it should warn
        $this->artisan('vurb:health')
            ->assertExitCode(1); // Some checks fail without daemon
    }

    // ═══ vurb:inspect ═══

    public function test_vurb_inspect_lists_all_tools(): void
    {
        $this->artisan('vurb:inspect')
            ->assertExitCode(0);
    }

    public function test_vurb_inspect_specific_tool(): void
    {
        $this->artisan('vurb:inspect', ['--tool' => 'customers.get_profile'])
            ->assertExitCode(0);
    }

    public function test_vurb_inspect_nonexistent_tool(): void
    {
        $this->artisan('vurb:inspect', ['--tool' => 'nonexistent.tool'])
            ->assertExitCode(1);
    }

    public function test_vurb_inspect_nonexistent_tool_with_close_match(): void
    {
        // 'customers' partially matches 'customers.get_profile' → triggers did-you-mean
        $this->artisan('vurb:inspect', ['--tool' => 'customers'])
            ->assertExitCode(1);
    }

    public function test_vurb_inspect_with_schema_flag(): void
    {
        $this->artisan('vurb:inspect', ['--tool' => 'customers.get_profile', '--schema' => true])
            ->assertExitCode(0);
    }

    public function test_vurb_inspect_with_demo_flag(): void
    {
        $this->artisan('vurb:inspect', ['--tool' => 'customers.get_profile', '--demo' => true])
            ->assertExitCode(0);
    }

    // ═══ vurb:make-tool ═══

    public function test_make_tool_creates_file(): void
    {
        $toolPath = config('vurb.tools.path') . '/TestMakeTool.php';

        // Ensure clean state
        if (file_exists($toolPath)) {
            unlink($toolPath);
        }

        $this->artisan('vurb:make-tool', ['name' => 'TestMakeTool'])
            ->assertExitCode(0);

        $this->assertFileExists($toolPath);
        $content = file_get_contents($toolPath);
        $this->assertStringContainsString('class TestMakeTool', $content);

        unlink($toolPath);
    }

    public function test_make_tool_query_variant(): void
    {
        $toolPath = config('vurb.tools.path') . '/TestQueryTool.php';
        if (file_exists($toolPath)) {
            unlink($toolPath);
        }

        $this->artisan('vurb:make-tool', ['name' => 'TestQueryTool', '--query' => true])
            ->assertExitCode(0);

        $this->assertFileExists($toolPath);
        $content = file_get_contents($toolPath);
        $this->assertStringContainsString('VurbQuery', $content);

        unlink($toolPath);
    }

    public function test_make_tool_mutation_variant(): void
    {
        $toolPath = config('vurb.tools.path') . '/TestMutationTool.php';
        if (file_exists($toolPath)) {
            unlink($toolPath);
        }

        $this->artisan('vurb:make-tool', ['name' => 'TestMutationTool', '--mutation' => true])
            ->assertExitCode(0);

        $this->assertFileExists($toolPath);

        unlink($toolPath);
    }

    public function test_make_tool_action_variant(): void
    {
        $toolPath = config('vurb.tools.path') . '/TestActionTool.php';
        if (file_exists($toolPath)) {
            unlink($toolPath);
        }

        $this->artisan('vurb:make-tool', ['name' => 'TestActionTool', '--action' => true])
            ->assertExitCode(0);

        $this->assertFileExists($toolPath);

        unlink($toolPath);
    }

    public function test_make_tool_fails_when_file_exists_without_force(): void
    {
        $toolPath = config('vurb.tools.path') . '/TestExistingTool.php';
        if (!is_dir(dirname($toolPath))) {
            mkdir(dirname($toolPath), 0755, true);
        }
        file_put_contents($toolPath, '<?php // existing');

        $this->artisan('vurb:make-tool', ['name' => 'TestExistingTool'])
            ->assertExitCode(1);

        unlink($toolPath);
    }

    public function test_make_tool_force_overwrites(): void
    {
        $toolPath = config('vurb.tools.path') . '/TestForceTool.php';
        if (!is_dir(dirname($toolPath))) {
            mkdir(dirname($toolPath), 0755, true);
        }
        file_put_contents($toolPath, '<?php // old');

        $this->artisan('vurb:make-tool', ['name' => 'TestForceTool', '--force' => true])
            ->assertExitCode(0);

        $content = file_get_contents($toolPath);
        $this->assertStringContainsString('class TestForceTool', $content);

        unlink($toolPath);
    }

    public function test_make_tool_router_variant(): void
    {
        $toolPath = config('vurb.tools.path') . '/TestRouter.php';
        if (file_exists($toolPath)) {
            unlink($toolPath);
        }

        $this->artisan('vurb:make-tool', ['name' => 'TestRouter', '--router' => true])
            ->assertExitCode(0);

        $this->assertFileExists($toolPath);
        $content = file_get_contents($toolPath);
        $this->assertStringContainsString('VurbRouter', $content);

        unlink($toolPath);
    }

    public function test_make_tool_with_subdirectory(): void
    {
        $basePath = config('vurb.tools.path');
        $toolPath = $basePath . DIRECTORY_SEPARATOR . 'SubGroup' . DIRECTORY_SEPARATOR . 'SubTool.php';

        if (file_exists($toolPath)) {
            unlink($toolPath);
        }

        $this->artisan('vurb:make-tool', ['name' => 'SubGroup/SubTool', '--query' => true])
            ->assertExitCode(0);

        $this->assertFileExists($toolPath);
        $content = file_get_contents($toolPath);
        $this->assertStringContainsString('class SubTool', $content);
        $this->assertStringContainsString('SubGroup', $content);

        unlink($toolPath);
        @rmdir(dirname($toolPath));
    }

    public function test_make_tool_router_fails_when_exists_without_force(): void
    {
        $toolPath = config('vurb.tools.path') . '/ExistingRouter.php';
        if (!is_dir(dirname($toolPath))) {
            mkdir(dirname($toolPath), 0755, true);
        }
        file_put_contents($toolPath, '<?php // existing router');

        $this->artisan('vurb:make-tool', ['name' => 'ExistingRouter', '--router' => true])
            ->assertExitCode(1);

        unlink($toolPath);
    }

    // ═══ vurb:make-presenter ═══

    public function test_make_presenter_creates_file(): void
    {
        $basePath = app_path('Vurb/Presenters');
        $path = $basePath . '/TestPresenter.php';

        if (file_exists($path)) {
            unlink($path);
        }

        $this->artisan('vurb:make-presenter', ['name' => 'TestPresenter'])
            ->assertExitCode(0);

        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('class TestPresenter', $content);
        $this->assertStringContainsString('VurbPresenter', $content);

        unlink($path);
    }

    public function test_make_presenter_with_collection(): void
    {
        $basePath = app_path('Vurb/Presenters');
        $path = $basePath . '/TestCollPresenter.php';
        $collPath = $basePath . '/TestCollPresenterCollection.php';

        foreach ([$path, $collPath] as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }

        $this->artisan('vurb:make-presenter', ['name' => 'TestCollPresenter', '--collection' => true])
            ->assertExitCode(0);

        $this->assertFileExists($path);
        $this->assertFileExists($collPath);

        $collContent = file_get_contents($collPath);
        $this->assertStringContainsString('VurbResourceCollection', $collContent);

        unlink($path);
        unlink($collPath);
    }

    public function test_make_presenter_fails_when_exists(): void
    {
        $basePath = app_path('Vurb/Presenters');
        $path = $basePath . '/TestExistingPresenter.php';

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
        file_put_contents($path, '<?php // existing');

        $this->artisan('vurb:make-presenter', ['name' => 'TestExistingPresenter'])
            ->assertExitCode(1);

        unlink($path);
    }

    public function test_make_presenter_with_subdirectory(): void
    {
        $basePath = app_path('Vurb/Presenters');
        $path = $basePath . '/SubDir/TestSubPresenter.php';

        if (file_exists($path)) {
            unlink($path);
        }

        $this->artisan('vurb:make-presenter', ['name' => 'SubDir/TestSubPresenter'])
            ->assertExitCode(0);

        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('class TestSubPresenter', $content);
        $this->assertStringContainsString('SubDir', $content);

        unlink($path);
        @rmdir(dirname($path));
    }

    // ═══ vurb:serve (pre-flight only) ═══

    public function test_vurb_serve_fails_without_node(): void
    {
        // Mock DaemonManager to report no node
        $daemon = $this->mock(\Vinkius\Vurb\Services\DaemonManager::class);
        $daemon->shouldReceive('isNodeAvailable')->once()->andReturn(false);

        $this->artisan('vurb:serve')
            ->assertExitCode(1);
    }

    public function test_vurb_serve_fails_without_token(): void
    {
        $daemon = $this->mock(\Vinkius\Vurb\Services\DaemonManager::class);
        $daemon->shouldReceive('isNodeAvailable')->once()->andReturn(true);

        config()->set('vurb.internal_token', '');

        $this->artisan('vurb:serve')
            ->assertExitCode(1);
    }
}
