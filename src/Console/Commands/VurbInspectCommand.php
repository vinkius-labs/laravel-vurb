<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Vinkius\Vurb\Services\ManifestCompiler;
use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Services\ToolDiscovery;

class VurbInspectCommand extends Command
{
    protected $signature = 'vurb:inspect
        {--tool= : Inspect a specific tool by name}
        {--schema : Show raw input schema}
        {--demo : Generate a demo call payload}';

    protected $description = 'Inspect registered tools, schemas, and the manifest';

    public function handle(ToolDiscovery $discovery, ManifestCompiler $compiler, ReflectionEngine $engine): int
    {
        $toolName = $this->option('tool');

        if ($toolName !== null) {
            return $this->inspectTool($discovery, $engine, $toolName);
        }

        return $this->inspectAll($discovery, $compiler);
    }

    protected function inspectAll(ToolDiscovery $discovery, ManifestCompiler $compiler): int
    {
        $manifest = $compiler->compile();

        $this->components->info('Registered Vurb Tools');
        $this->newLine();

        $rows = [];
        foreach ($manifest['tools'] ?? [] as $namespace => $tools) {
            foreach ($tools as $tool) {
                $name = $tool['name'] ?? ($namespace . '.?');
                $verb = $tool['annotations']['verb'] ?? $tool['verb'] ?? 'query';
                $params = count((array) ($tool['inputSchema']['properties'] ?? []));
                $presenter = $tool['presenter'] ?? '-';
                $middleware = count($tool['middleware'] ?? []);

                $rows[] = [
                    $name,
                    $verb,
                    $params,
                    $presenter !== '-' ? class_basename($presenter) : '-',
                    $middleware,
                ];
            }
        }

        $this->table(
            ['Tool', 'Verb', 'Params', 'Presenter', 'Middleware'],
            $rows
        );

        $this->newLine();
        $this->components->twoColumnDetail('Total', (string) count($rows));

        return self::SUCCESS;
    }

    protected function inspectTool(ToolDiscovery $discovery, ReflectionEngine $engine, string $toolName): int
    {
        $entry = $discovery->findTool($toolName);

        if ($entry === null) {
            $this->components->error("Tool not found: {$toolName}");

            // Suggest close matches
            $allNames = $discovery->toolNames();
            $suggestions = array_filter($allNames, fn ($n) => str_contains($n, $toolName) || str_contains($toolName, $n));

            if (! empty($suggestions)) {
                $this->components->info('Did you mean?');
                $this->components->bulletList(array_values($suggestions));
            }

            return self::FAILURE;
        }

        // Reflect the tool to get full schema info
        $tool = $engine->reflectTool($entry['tool']);

        $this->components->info("Tool: {$toolName}");
        $this->newLine();

        $this->components->twoColumnDetail('Name', $toolName);
        $this->components->twoColumnDetail('Description', $tool['description'] ?? '-');
        $this->components->twoColumnDetail('Verb', $tool['annotations']['verb'] ?? $tool['verb'] ?? 'query');
        $this->components->twoColumnDetail('Class', $tool['class'] ?? '-');

        // Instructions
        if (! empty($tool['instructions'])) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=cyan>Instructions</>', '');
            $this->line("  " . $tool['instructions']);
        }

        // Input Schema
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Input Schema</>', '');

        $properties = $tool['inputSchema']['properties'] ?? [];
        $required = $tool['inputSchema']['required'] ?? [];

        foreach ($properties as $param => $schema) {
            $type = $schema['type'] ?? 'any';
            $isRequired = in_array($param, $required, true);
            $desc = $schema['description'] ?? '';
            $example = isset($schema['example']) ? " (e.g. {$schema['example']})" : '';

            $requiredTag = $isRequired ? ' <fg=red>*</>' : '';
            $this->components->twoColumnDetail(
                "  {$param}{$requiredTag} <fg=gray>{$type}</>",
                $desc . $example
            );
        }

        // Annotations
        $annotations = $tool['annotations'] ?? [];
        if (! empty($annotations)) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=cyan>Annotations</>', '');

            foreach ($annotations as $key => $value) {
                $display = is_array($value) ? json_encode($value) : (string) $value;
                $this->components->twoColumnDetail("  {$key}", $display);
            }
        }

        // Demo payload
        if ($this->option('demo') || $this->option('schema')) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=cyan>Schema JSON</>', '');
            $this->line(json_encode($tool['inputSchema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($this->option('demo')) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=cyan>Demo Payload</>', '');
            $payload = $this->buildDemoPayload($tool['inputSchema']);
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    protected function buildDemoPayload(array $schema): array
    {
        $payload = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $name => $prop) {
            $payload[$name] = $this->generateDemoValue($prop);
        }

        return $payload;
    }

    protected function generateDemoValue(array $prop): mixed
    {
        if (isset($prop['example'])) {
            return $prop['example'];
        }

        if (isset($prop['enum'])) {
            return $prop['enum'][0] ?? null;
        }

        return match ($prop['type'] ?? 'string') {
            'string' => 'example',
            'integer' => 1,
            'number' => 1.0,
            'boolean' => true,
            'array' => [],
            'object' => (object) [],
            default => null,
        };
    }
}
