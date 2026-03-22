<?php

namespace Vinkius\Vurb;

use Vinkius\Vurb\Models\ModelRegistry;
use Vinkius\Vurb\Presenters\PresenterRegistry;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\HealthCheck;
use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Services\ToolDiscovery;

class VurbManager
{
    public function __construct(
        protected ToolDiscovery $discovery,
        protected ManifestCompiler $compiler,
        protected DaemonManager $daemon,
        protected HealthCheck $health,
        protected PresenterRegistry $presenterRegistry,
        protected ModelRegistry $modelRegistry,
    ) {}

    public function discover(): array
    {
        return $this->discovery->discover();
    }

    public function compileManifest(): array
    {
        return $this->compiler->compile();
    }

    public function isHealthy(): bool
    {
        return $this->health->check();
    }

    public function discovery(): ToolDiscovery
    {
        return $this->discovery;
    }

    public function compiler(): ManifestCompiler
    {
        return $this->compiler;
    }

    public function daemon(): DaemonManager
    {
        return $this->daemon;
    }

    public function health(): HealthCheck
    {
        return $this->health;
    }

    public function presenters(): PresenterRegistry
    {
        return $this->presenterRegistry;
    }

    public function models(): ModelRegistry
    {
        return $this->modelRegistry;
    }
}
