<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Governance\LockfileGenerator;
use Vinkius\Vurb\Tests\TestCase;

class LockfileGeneratorTest extends TestCase
{
    protected LockfileGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(LockfileGenerator::class);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('vurb.server.name', 'test-server');
        $app['config']->set('vurb.server.version', '1.0.0');
        $app['config']->set('vurb.server.description', 'Test');
        $app['config']->set('vurb.bridge.base_url', 'http://localhost');
        $app['config']->set('vurb.bridge.prefix', '/_vurb');
        $app['config']->set('vurb.exposition', 'flat');
        $app['config']->set('vurb.state_sync.default', 'stale');
        $app['config']->set('vurb.state_sync.policies', []);
        $app['config']->set('vurb.fsm', null);
    }

    public function test_generate_returns_valid_structure(): void
    {
        $lockfile = $this->generator->generate();

        $this->assertArrayHasKey('version', $lockfile);
        $this->assertSame('1.0', $lockfile['version']);
        $this->assertArrayHasKey('serverName', $lockfile);
        $this->assertSame('test-server', $lockfile['serverName']);
        $this->assertArrayHasKey('serverDigest', $lockfile);
        $this->assertArrayHasKey('tools', $lockfile);
        $this->assertArrayHasKey('generatedAt', $lockfile);
    }

    public function test_server_digest_is_sha256(): void
    {
        $lockfile = $this->generator->generate();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $lockfile['serverDigest']);
    }

    public function test_tool_digests_contain_surface_and_behavior(): void
    {
        $lockfile = $this->generator->generate();

        foreach ($lockfile['tools'] as $toolName => $digest) {
            $this->assertArrayHasKey('surface', $digest, "Tool {$toolName} missing 'surface'");
            $this->assertArrayHasKey('behavior', $digest, "Tool {$toolName} missing 'behavior'");
            $this->assertArrayHasKey('inputSchema', $digest['surface']);
        }
    }

    public function test_generate_and_write_creates_file(): void
    {
        $path = sys_get_temp_dir() . '/vurb-test-lock-' . uniqid() . '.lock';

        try {
            $resultPath = $this->generator->generateAndWrite($path);

            $this->assertSame($path, $resultPath);
            $this->assertFileExists($path);

            $contents = json_decode(file_get_contents($path), true);
            $this->assertSame('1.0', $contents['version']);
        } finally {
            @unlink($path);
        }
    }

    public function test_check_returns_true_when_matching(): void
    {
        $path = sys_get_temp_dir() . '/vurb-test-lock-' . uniqid() . '.lock';

        try {
            $this->generator->generateAndWrite($path);

            // Should match since nothing changed
            $this->assertTrue($this->generator->check($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_check_returns_false_when_no_lockfile(): void
    {
        $this->assertFalse($this->generator->check('/nonexistent/path/vurb.lock'));
    }

    public function test_digest_changes_when_tools_change(): void
    {
        $lockfile1 = $this->generator->generate();
        $digest1 = $lockfile1['serverDigest'];

        // Change server name which changes the manifest
        $this->app['config']->set('vurb.server.name', 'different-server');
        $generator2 = $this->app->make(LockfileGenerator::class);
        $lockfile2 = $generator2->generate();
        $digest2 = $lockfile2['serverDigest'];

        $this->assertNotSame($digest1, $digest2);
    }
}
