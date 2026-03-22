<?php

namespace Vinkius\Vurb\Facades;

use Illuminate\Support\Facades\Facade;
use Vinkius\Vurb\VurbManager;

/**
 * @method static array discover()
 * @method static array compileManifest()
 * @method static bool isHealthy()
 * @method static \Vinkius\Vurb\Services\ToolDiscovery discovery()
 * @method static \Vinkius\Vurb\Services\ManifestCompiler compiler()
 * @method static \Vinkius\Vurb\Services\DaemonManager daemon()
 * @method static \Vinkius\Vurb\Services\HealthCheck health()
 * @method static \Vinkius\Vurb\Presenters\PresenterRegistry presenters()
 * @method static \Vinkius\Vurb\Models\ModelRegistry models()
 *
 * @see \Vinkius\Vurb\VurbManager
 */
class Vurb extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VurbManager::class;
    }
}
