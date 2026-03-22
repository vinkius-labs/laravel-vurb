<?php

namespace Vinkius\Vurb\Testing;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Vinkius\Vurb\VurbServiceProvider;

abstract class VurbTestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            VurbServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Vurb' => \Vinkius\Vurb\Facades\Vurb::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('vurb.internal_token', 'test_token_' . str_repeat('x', 32));
        $app['config']->set('vurb.tools.path', $app->basePath('app/Vurb/Tools'));
        $app['config']->set('vurb.tools.namespace', 'App\\Vurb\\Tools');
    }

    /**
     * Create a FakeVurbTester for a tool class.
     */
    protected function vurbTester(string $toolClass): FakeVurbTester
    {
        return FakeVurbTester::for($toolClass);
    }
}
