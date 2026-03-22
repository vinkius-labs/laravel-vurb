<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Vinkius\Vurb\Services\ManifestCompiler;

class VurbManifestCommand extends Command
{
    protected $signature = 'vurb:manifest
        {--json : Output raw JSON}
        {--write : Write manifest to disk}';

    protected $description = 'Compile and display the Schema Manifest';

    public function handle(ManifestCompiler $compiler): int
    {
        $manifest = $compiler->compile();

        if ($this->option('write')) {
            $path = $compiler->compileAndWrite();
            $this->components->info("Manifest written to: {$path}");

            if (! $this->option('json')) {
                return self::SUCCESS;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        // Pretty display
        $this->displayManifest($manifest);

        return self::SUCCESS;
    }

    protected function displayManifest(array $manifest): void
    {
        $this->components->twoColumnDetail('<fg=green;options=bold>Server</>', '');
        $this->components->twoColumnDetail('  Name', $manifest['server']['name'] ?? '-');
        $this->components->twoColumnDetail('  Version', $manifest['server']['version'] ?? '-');
        $this->components->twoColumnDetail('  Description', $manifest['server']['description'] ?? '-');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Bridge</>', '');
        $this->components->twoColumnDetail('  Base URL', $manifest['bridge']['baseUrl'] ?? '-');
        $this->components->twoColumnDetail('  Prefix', $manifest['bridge']['prefix'] ?? '-');
        $this->components->twoColumnDetail('  Token', ! empty($manifest['bridge']['token']) ? '***set***' : '<fg=red>NOT SET</>');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Tools</>', '');

        $tools = $manifest['tools'] ?? [];
        $totalTools = 0;

        foreach ($tools as $namespace => $namespacedTools) {
            $this->components->twoColumnDetail("  <fg=cyan>{$namespace}</>", count($namespacedTools) . ' tools');

            foreach ($namespacedTools as $tool) {
                $totalTools++;
                $name = $tool['name'] ?? '?';
                $verb = $tool['annotations']['verb'] ?? 'query';
                $desc = $tool['description'] ?? '';
                $truncDesc = mb_strlen($desc) > 50 ? mb_substr($desc, 0, 50) . '...' : $desc;

                $verbColor = match ($verb) {
                    'query' => 'blue',
                    'mutation' => 'yellow',
                    'action' => 'magenta',
                    default => 'white',
                };

                $this->components->twoColumnDetail(
                    "    <fg={$verbColor}>{$verb}</> {$name}",
                    $truncDesc
                );
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Total tools', (string) $totalTools);

        $presenters = $manifest['presenters'] ?? [];
        $models = $manifest['models'] ?? [];
        $this->components->twoColumnDetail('Presenters', (string) count($presenters));
        $this->components->twoColumnDetail('Models', (string) count($models));
    }
}
