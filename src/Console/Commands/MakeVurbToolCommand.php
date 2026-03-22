<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeVurbToolCommand extends Command
{
    protected $signature = 'vurb:make-tool {name : The tool class name}
        {--query : Create a VurbQuery (read-only)}
        {--mutation : Create a VurbMutation (write)}
        {--action : Create a VurbAction (side-effect)}
        {--router : Create a VurbRouter for grouping tools}
        {--force : Overwrite existing file}';

    protected $description = 'Create a new Vurb tool class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $isRouter = $this->option('router');

        if ($isRouter) {
            return $this->createRouter($name);
        }

        $baseClass = $this->resolveBaseClass();
        $verb = $this->resolveVerb();

        $path = $this->resolveFilePath($name);

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error("Tool already exists: {$path}");
            return self::FAILURE;
        }

        $stub = $this->buildToolStub($name, $baseClass, $verb);

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $stub);

        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        $this->components->info("Tool created: {$relativePath}");

        return self::SUCCESS;
    }

    protected function resolveBaseClass(): string
    {
        if ($this->option('query')) {
            return 'VurbQuery';
        }

        if ($this->option('mutation')) {
            return 'VurbMutation';
        }

        if ($this->option('action')) {
            return 'VurbAction';
        }

        return 'VurbTool';
    }

    protected function resolveVerb(): string
    {
        if ($this->option('query')) {
            return 'query';
        }

        if ($this->option('mutation')) {
            return 'mutation';
        }

        if ($this->option('action')) {
            return 'action';
        }

        return 'query';
    }

    protected function resolveFilePath(string $name): string
    {
        $parts = explode('/', str_replace('\\', '/', $name));
        $fileName = array_pop($parts);
        $subDir = implode(DIRECTORY_SEPARATOR, $parts);

        $basePath = config('vurb.tools.path', app_path('Vurb/Tools'));

        if ($subDir !== '') {
            return $basePath . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . $fileName . '.php';
        }

        return $basePath . DIRECTORY_SEPARATOR . $fileName . '.php';
    }

    protected function resolveNamespace(string $name): string
    {
        $parts = explode('/', str_replace('\\', '/', $name));
        array_pop($parts); // remove class name

        $baseNamespace = config('vurb.tools.namespace', 'App\\Vurb\\Tools');

        if (! empty($parts)) {
            return $baseNamespace . '\\' . implode('\\', $parts);
        }

        return $baseNamespace;
    }

    protected function resolveClassName(string $name): string
    {
        $parts = explode('/', str_replace('\\', '/', $name));
        return array_pop($parts);
    }

    protected function buildToolStub(string $name, string $baseClass, string $verb): string
    {
        $namespace = $this->resolveNamespace($name);
        $className = $this->resolveClassName($name);
        $descName = Str::headline($className);

        // Try to load stub file
        $stubName = match ($verb) {
            'query' => 'tool.query.stub',
            'mutation' => 'tool.mutation.stub',
            'action' => 'tool.action.stub',
            default => 'tool.stub',
        };

        $stubPath = dirname(__DIR__, 3) . '/resources/stubs/' . $stubName;

        if (file_exists($stubPath)) {
            $content = file_get_contents($stubPath);
            return str_replace(
                ['{{ namespace }}', '{{ class }}', '{{ description }}'],
                [$namespace, $className, $descName],
                $content,
            );
        }

        // Fallback to inline generation
        $baseImport = "Vinkius\\Vurb\\Tools\\{$baseClass}";

        return <<<PHP
<?php

namespace {$namespace};

use Vinkius\\Vurb\\Attributes\\Description;
use Vinkius\\Vurb\\Attributes\\Param;
use {$baseImport};

#[Description('{$descName}')]
class {$className} extends {$baseClass}
{
    public function handle(): mixed
    {
        // TODO: Implement tool logic
        return [];
    }
}
PHP;
    }

    protected function createRouter(string $name): int
    {
        $path = $this->resolveFilePath($name);

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error("Router already exists: {$path}");
            return self::FAILURE;
        }

        $namespace = $this->resolveNamespace($name);
        $className = $this->resolveClassName($name);
        $prefix = Str::snake($className, '.');

        $stub = <<<PHP
<?php

namespace {$namespace};

use Vinkius\\Vurb\\Tools\\VurbRouter;

class {$className} extends VurbRouter
{
    public string \$prefix = '{$prefix}';

    public string \$description = '{$className} tool group';

    public array \$middleware = [];
}
PHP;

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $stub);

        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        $this->components->info("Router created: {$relativePath}");

        return self::SUCCESS;
    }
}
