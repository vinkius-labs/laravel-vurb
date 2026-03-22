<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Testing\FakeVurbTester;
use Vinkius\Vurb\Testing\VurbTestCase;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;

class VurbTestCaseTest extends VurbTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Override tools path to our test fixtures
        $app['config']->set('vurb.tools.path', __DIR__ . '/../Fixtures/Tools');
        $app['config']->set('vurb.tools.namespace', 'Vinkius\\Vurb\\Tests\\Fixtures\\Tools');
    }

    public function test_get_package_providers_returns_service_provider(): void
    {
        $providers = $this->getPackageProviders($this->app);
        $this->assertContains(\Vinkius\Vurb\VurbServiceProvider::class, $providers);
    }

    public function test_get_package_aliases_returns_vurb_facade(): void
    {
        $aliases = $this->getPackageAliases($this->app);
        $this->assertArrayHasKey('Vurb', $aliases);
        $this->assertSame(\Vinkius\Vurb\Facades\Vurb::class, $aliases['Vurb']);
    }

    public function test_vurb_tester_returns_fake_vurb_tester(): void
    {
        $tester = $this->vurbTester(GetCustomerProfile::class);
        $this->assertInstanceOf(FakeVurbTester::class, $tester);
    }

    public function test_vurb_tester_can_execute_tool(): void
    {
        $tester = $this->vurbTester(GetCustomerProfile::class);
        $result = $tester->call(['id' => 1]);

        $result->assertSuccessful();
        $result->assertDataHasKey('name');

        $this->assertFalse($result->isError);
        $this->assertSame('John Doe', $result->data['name']);
    }
}
