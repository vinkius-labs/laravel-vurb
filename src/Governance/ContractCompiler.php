<?php

namespace Vinkius\Vurb\Governance;

use Vinkius\Vurb\Services\ManifestCompiler;

class ContractCompiler
{
    public function __construct(
        protected ManifestCompiler $compiler,
    ) {}

    /**
     * Compile all tool contracts (surface + behavior).
     */
    public function compileContracts(): array
    {
        $manifest = $this->compiler->compile();
        $contracts = [];

        foreach ($manifest['tools'] ?? [] as $namespace => $tools) {
            foreach ($tools as $tool) {
                $toolName = $tool['name'] ?? ($namespace . '.unknown');

                $contracts[$toolName] = [
                    'name' => $toolName,
                    'verb' => $tool['annotations']['verb'] ?? $tool['verb'] ?? 'action',
                    'description' => $tool['description'] ?? '',
                    'inputSchema' => $tool['inputSchema'] ?? [],
                    'annotations' => $tool['annotations'] ?? [],
                    'tags' => $tool['tags'] ?? [],
                    'stateSync' => $tool['stateSync'] ?? null,
                    'fsmBind' => $tool['fsmBind'] ?? null,
                    'presenter' => $tool['presenter'] ?? null,
                    'middleware' => $tool['middleware'] ?? [],
                    'digest' => hash('sha256', json_encode($tool)),
                ];
            }
        }

        return $contracts;
    }

    /**
     * Get a single tool contract.
     */
    public function getContract(string $toolName): ?array
    {
        $contracts = $this->compileContracts();

        return $contracts[$toolName] ?? null;
    }
}
