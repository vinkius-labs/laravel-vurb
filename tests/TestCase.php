<?php

namespace Vinkius\Vurb\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Vinkius\Vurb\VurbServiceProvider;

abstract class TestCase extends OrchestraTestCase
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
        $app['config']->set('vurb.internal_token', 'test_token_' . str_repeat('a', 32));
        $app['config']->set('vurb.tools.path', __DIR__ . '/Fixtures/Tools');
        $app['config']->set('vurb.tools.namespace', 'Vinkius\\Vurb\\Tests\\Fixtures\\Tools');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
