<?php

namespace Vinkius\Vurb;

use Illuminate\Support\ServiceProvider;
use Vinkius\Vurb\Console\Commands\MakeVurbPresenterCommand;
use Vinkius\Vurb\Console\Commands\MakeVurbToolCommand;
use Vinkius\Vurb\Console\Commands\VurbHealthCommand;
use Vinkius\Vurb\Console\Commands\VurbInspectCommand;
use Vinkius\Vurb\Console\Commands\VurbInstallCommand;
use Vinkius\Vurb\Console\Commands\VurbLockCommand;
use Vinkius\Vurb\Console\Commands\VurbManifestCommand;
use Vinkius\Vurb\Console\Commands\VurbServeCommand;
use Vinkius\Vurb\Governance\DynamicManifest;
use Vinkius\Vurb\Http\Middleware\ValidateVurbToken;
use Vinkius\Vurb\Models\ModelRegistry;
use Vinkius\Vurb\Presenters\PresenterRegistry;
use Vinkius\Vurb\Security\DlpRedactor;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\HealthCheck;
use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Services\ToolDiscovery;
use Vinkius\Vurb\Skills\SkillsRegistry;

class VurbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/vurb.php', 'vurb');

        $this->app->singleton(ReflectionEngine::class);
        $this->app->singleton(ToolDiscovery::class);
        $this->app->singleton(ManifestCompiler::class);
        $this->app->singleton(PresenterRegistry::class);
        $this->app->singleton(ModelRegistry::class);
        $this->app->singleton(DaemonManager::class);
        $this->app->singleton(HealthCheck::class);
        $this->app->singleton(DlpRedactor::class);
        $this->app->singleton(DynamicManifest::class);
        $this->app->singleton(SkillsRegistry::class);

        $this->app->singleton(VurbManager::class, function ($app) {
            return new VurbManager(
                $app->make(ToolDiscovery::class),
                $app->make(ManifestCompiler::class),
                $app->make(DaemonManager::class),
                $app->make(HealthCheck::class),
                $app->make(PresenterRegistry::class),
                $app->make(ModelRegistry::class),
            );
        });
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMigrations();
        $this->registerEvents();
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/vurb.php');
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/vurb.php' => config_path('vurb.php'),
            ], 'vurb-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'vurb-migrations');

            $this->publishes([
                __DIR__ . '/../llms.txt' => base_path('llms.txt'),
            ], 'vurb-llms');

            $this->publishes([
                __DIR__ . '/../resources/skills/laravel-vurb-development' => base_path('.claude/skills/laravel-vurb-development'),
            ], 'vurb-skills');
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                VurbInstallCommand::class,
                VurbServeCommand::class,
                MakeVurbToolCommand::class,
                MakeVurbPresenterCommand::class,
                VurbManifestCommand::class,
                VurbLockCommand::class,
                VurbHealthCommand::class,
                VurbInspectCommand::class,
            ]);
        }
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function registerEvents(): void
    {
        if (! config('vurb.observability.events', true)) {
            return;
        }

        // Events are dispatched by the bridge controller and services.
        // Telescope/Pulse watchers listen to these automatically.
    }
}
