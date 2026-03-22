<?php

namespace Vinkius\Vurb\Governance;

use Illuminate\Support\Facades\File;
use Vinkius\Vurb\Services\ManifestCompiler;

class LockfileGenerator
{
    public function __construct(
        protected ManifestCompiler $compiler,
    ) {}

    /**
     * Generate a vurb.lock file with SHA-256 digests of all contracts.
     */
    public function generate(): array
    {
        $manifest = $this->compiler->compile();

        $lockfile = [
            'version' => '1.0',
            'serverName' => $manifest['server']['name'] ?? 'unknown',
            'serverDigest' => hash('sha256', json_encode($manifest)),
            'tools' => $this->buildToolDigests($manifest),
            'generatedAt' => now()->toIso8601String(),
        ];

        return $lockfile;
    }

    /**
     * Generate and write the lockfile to disk.
     */
    public function generateAndWrite(?string $path = null): string
    {
        $lockfile = $this->generate();
        $path = $path ?? base_path('vurb.lock');

        File::put($path, json_encode($lockfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Check if the current state matches the lockfile.
     */
    public function check(?string $path = null): bool
    {
        $path = $path ?? base_path('vurb.lock');

        if (! File::exists($path)) {
            return false;
        }

        $existing = json_decode(File::get($path), true);
        $current = $this->generate();

        return $existing['serverDigest'] === $current['serverDigest'];
    }

    /**
     * Build per-tool contract digests.
     */
    protected function buildToolDigests(array $manifest): array
    {
        $digests = [];

        foreach ($manifest['tools'] ?? [] as $namespace => $tools) {
            foreach ($tools as $tool) {
                $toolName = $tool['name'] ?? ($namespace . '.unknown');

                $digests[$toolName] = [
                    'surface' => [
                        'inputSchema' => $tool['inputSchema'] ?? [],
                        'requiredFields' => $tool['inputSchema']['required'] ?? [],
                    ],
                    'behavior' => $tool['annotations'] ?? [],
                ];
            }
        }

        return $digests;
    }
}
