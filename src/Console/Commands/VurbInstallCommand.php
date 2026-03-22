<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VurbInstallCommand extends Command
{
    protected $signature = 'vurb:install {--force : Overwrite existing config}';

    protected $description = 'Install the Vurb MCP bridge: publish config, create directories, install deps, generate token, run migrations';

    public function handle(): int
    {
        $this->components->info('Installing Laravel Vurb...');

        // 1. Publish config
        $this->publishConfig();

        // 2. Create directory structure
        $this->createDirectories();

        // 3. Publish AI documentation (llms.txt + skills)
        $this->publishAiDocs();

        // 4. Install daemon npm dependencies
        $this->installNpmDependencies();

        // 5. Generate internal token
        $this->generateToken();

        // 6. Run migrations
        $this->runMigrations();

        // 7. Summary
        $this->printSummary();

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->components->task('Publishing config', function () {
            $params = ['--provider' => 'Vinkius\Vurb\VurbServiceProvider', '--tag' => 'vurb-config'];

            if ($this->option('force')) {
                $params['--force'] = true;
            }

            $this->callSilently('vendor:publish', $params);
        });
    }

    protected function createDirectories(): void
    {
        $dirs = [
            app_path('Vurb/Tools'),
            app_path('Vurb/Middleware'),
            app_path('Vurb/Presenters'),
        ];

        $this->components->task('Creating directory structure', function () use ($dirs) {
            foreach ($dirs as $dir) {
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
        });
    }

    protected function publishAiDocs(): void
    {
        $this->components->task('Publishing llms.txt', function () {
            $this->callSilently('vendor:publish', [
                '--provider' => 'Vinkius\Vurb\VurbServiceProvider',
                '--tag' => 'vurb-llms',
                '--force' => true,
            ]);
        });

        $this->components->task('Publishing AI skills (.claude/skills/)', function () {
            $this->callSilently('vendor:publish', [
                '--provider' => 'Vinkius\Vurb\VurbServiceProvider',
                '--tag' => 'vurb-skills',
                '--force' => true,
            ]);
        });
    }

    protected function installNpmDependencies(): void
    {
        $daemonManager = app(\Vinkius\Vurb\Services\DaemonManager::class);

        if (! $daemonManager->isNodeAvailable()) {
            $this->components->warn('Node.js not found — skipping npm install. Install Node.js 18+ to use the daemon.');
            return;
        }

        $nodeVersion = $daemonManager->getNodeVersion();
        $this->components->task("Installing daemon dependencies (Node {$nodeVersion})", function () use ($daemonManager) {
            return $daemonManager->installDependencies();
        });
    }

    protected function generateToken(): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            $this->components->warn('.env file not found — skipping token generation.');
            return;
        }

        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, 'VURB_INTERNAL_TOKEN=') && ! $this->option('force')) {
            $this->components->task('Internal token', fn () => true);
            $this->components->info('  Token already set. Use --force to regenerate.');
            return;
        }

        $this->components->task('Generating VURB_INTERNAL_TOKEN', function () use ($envPath, $envContent) {
            $token = 'vurb_local_' . Str::random(48);

            if (str_contains($envContent, 'VURB_INTERNAL_TOKEN=')) {
                $envContent = preg_replace(
                    '/^VURB_INTERNAL_TOKEN=.*/m',
                    "VURB_INTERNAL_TOKEN={$token}",
                    $envContent
                );
            } else {
                $envContent .= "\nVURB_INTERNAL_TOKEN={$token}\n";
            }

            file_put_contents($envPath, $envContent);
        });
    }

    protected function runMigrations(): void
    {
        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate', ['--force' => true]);
        });
    }

    protected function printSummary(): void
    {
        $this->newLine();
        $this->components->info('Vurb installed successfully!');
        $this->newLine();
        $this->components->bulletList([
            'Create tools in <comment>app/Vurb/Tools/</comment>',
            'Run <comment>php artisan vurb:serve</comment> to start the MCP daemon',
            'Scaffold tools with <comment>php artisan vurb:make-tool UserProfile --query</comment>',
            '<comment>llms.txt</comment> published to project root (AI context)',
            'Skills published to <comment>.claude/skills/laravel-vurb-development/</comment>',
        ]);
    }
}
