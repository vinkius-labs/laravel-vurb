<?php

namespace Vinkius\Vurb\Skills;

use Illuminate\Support\Facades\File;

class SkillsRegistry
{
    protected array $skills = [];

    /**
     * Scan a directory for SKILL.md files and register them.
     */
    public function discover(?string $basePath = null): array
    {
        $basePath = $basePath ?? app_path('Vurb/Skills');

        if (! is_dir($basePath)) {
            return [];
        }

        $this->skills = [];

        $directories = File::directories($basePath);

        foreach ($directories as $dir) {
            $skillFile = $dir . DIRECTORY_SEPARATOR . 'SKILL.md';

            if (file_exists($skillFile)) {
                $name = basename($dir);
                $content = file_get_contents($skillFile);
                $description = $this->extractDescription($content);

                $this->skills[$name] = [
                    'name' => $name,
                    'path' => $skillFile,
                    'description' => $description,
                    'content' => $content,
                ];
            }
        }

        return $this->skills;
    }

    /**
     * Get all registered skills.
     */
    public function all(): array
    {
        return $this->skills;
    }

    /**
     * Get a specific skill by name.
     */
    public function get(string $name): ?array
    {
        return $this->skills[$name] ?? null;
    }

    /**
     * Compile skills for the Schema Manifest.
     */
    public function compileAll(): array
    {
        $compiled = [];

        foreach ($this->skills as $name => $skill) {
            $compiled[] = [
                'name' => $name,
                'description' => $skill['description'],
            ];
        }

        return $compiled;
    }

    /**
     * Register a skill manually.
     */
    public function register(string $name, string $description, string $content): void
    {
        $this->skills[$name] = [
            'name' => $name,
            'path' => null,
            'description' => $description,
            'content' => $content,
        ];
    }

    /**
     * Extract the first paragraph or heading as a description from SKILL.md content.
     */
    protected function extractDescription(string $content): string
    {
        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip headings
            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            // Return first non-empty line as description
            if ($trimmed !== '') {
                return mb_strlen($trimmed) > 200 ? mb_substr($trimmed, 0, 200) . '...' : $trimmed;
            }
        }

        return '';
    }
}
