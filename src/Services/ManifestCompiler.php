<?php

namespace Vinkius\Vurb\Services;

use Illuminate\Support\Facades\File;
use Vinkius\Vurb\Models\ModelRegistry;
use Vinkius\Vurb\Presenters\PresenterRegistry;

class ManifestCompiler
{
    public function __construct(
        protected ToolDiscovery $discovery,
        protected ReflectionEngine $reflection,
        protected PresenterRegistry $presenterRegistry,
        protected ModelRegistry $modelRegistry,
    ) {}

    /**
     * Compile the full Schema Manifest JSON for the daemon.
     */
    public function compile(): array
    {
        $tools = $this->discovery->discover();

        // Group tools by namespace
        $grouped = $this->groupToolsByNamespace($tools);

        // Build the manifest
        $manifest = [
            'version' => '1.0',
            'server' => [
                'name' => config('vurb.server.name'),
                'version' => config('vurb.server.version'),
                'description' => config('vurb.server.description'),
            ],
            'bridge' => [
                'baseUrl' => config('vurb.bridge.base_url'),
                'prefix' => config('vurb.bridge.prefix'),
                'token' => config('vurb.internal_token'),
            ],
            'toolExposition' => config('vurb.exposition', 'flat'),
            'tools' => $this->buildToolsSection($grouped),
            'presenters' => $this->presenterRegistry->compileAll(),
            'models' => $this->modelRegistry->compileAll(),
            'stateSync' => $this->buildStateSyncSection(),
            'fsm' => config('vurb.fsm'),
            'skills' => $this->buildSkillsSection(),
        ];

        return $manifest;
    }

    /**
     * Compile and write the manifest to disk.
     */
    public function compileAndWrite(): string
    {
        $manifest = $this->compile();
        $path = config('vurb.daemon.manifest_path');

        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Group tools by namespace as Record<string, ManifestTool[]> for vurb.ts daemon.
     */
    protected function groupToolsByNamespace(array $tools): array
    {
        $grouped = [];

        foreach ($tools as $name => $entry) {
            $parts = explode('.', $name, 2);
            $namespace = count($parts) === 2 ? $parts[0] : 'default';

            if (! isset($grouped[$namespace])) {
                $grouped[$namespace] = [];
            }

            $schema = $this->reflection->reflectTool($entry['tool']);

            // Set full tool name (namespace.action) for daemon URL building
            $schema['name'] = $name;
            unset($schema['key']);

            // Override tags with merged tags from discovery
            $schema['tags'] = $entry['tags'];

            // Include middleware class names from discovery
            $schema['middleware'] = array_map(
                fn ($m) => is_string($m) ? $m : get_class($m),
                $entry['middleware'] ?? [],
            );

            $grouped[$namespace][] = $schema;
        }

        return $grouped;
    }

    /**
     * Build the tools section of the manifest.
     */
    protected function buildToolsSection(array $grouped): array
    {
        return $grouped;
    }

    /**
     * Build the state sync section from config.
     */
    protected function buildStateSyncSection(): array
    {
        return [
            'default' => config('vurb.state_sync.default', 'stale'),
            'policies' => $this->buildStateSyncPolicies(),
        ];
    }

    /**
     * Build state sync policies as Record<string, StateSyncPolicy> for vurb.ts.
     */
    protected function buildStateSyncPolicies(): array
    {
        $configPolicies = config('vurb.state_sync.policies', []);
        $policies = [];

        foreach ($configPolicies as $pattern => $policy) {
            $entry = [];

            if (isset($policy['directive'])) {
                $entry['directive'] = $policy['directive'];
            }

            if (isset($policy['invalidates'])) {
                $entry['invalidates'] = $policy['invalidates'];
            }

            $policies[$pattern] = $entry;
        }

        return $policies;
    }

    /**
     * Build the skills section by scanning app/Vurb/Skills/.
     */
    protected function buildSkillsSection(): array
    {
        $registry = app(\Vinkius\Vurb\Skills\SkillsRegistry::class);
        $registry->discover();

        return $registry->compileAll();
    }
}
