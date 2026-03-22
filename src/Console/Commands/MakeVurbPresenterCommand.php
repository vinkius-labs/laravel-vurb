<?php

namespace Vinkius\Vurb\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeVurbPresenterCommand extends Command
{
    protected $signature = 'vurb:make-presenter {name : The presenter class name}
        {--collection : Also create a resource collection}
        {--force : Overwrite existing file}';

    protected $description = 'Create a new Vurb presenter (JsonResource with MVA methods)';

    public function handle(): int
    {
        $name = $this->argument('name');

        $path = $this->resolveFilePath($name);

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error("Presenter already exists: {$path}");
            return self::FAILURE;
        }

        $namespace = $this->resolveNamespace($name);
        $className = $this->resolveClassName($name);

        $stub = <<<PHP
<?php

namespace {$namespace};

use Vinkius\\Vurb\\Presenters\\VurbPresenter;

class {$className} extends VurbPresenter
{
    public function toArray(\$request): array
    {
        return [
            'id' => \$this->id,
            // TODO: Define your resource fields
        ];
    }

    public function systemRules(): array
    {
        return [
            // 'Always display the name in bold.',
        ];
    }

    public function uiBlocks(): array
    {
        return [];
    }

    public function suggestActions(): array
    {
        return [];
    }
}
PHP;

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $stub);

        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        $this->components->info("Presenter created: {$relativePath}");

        if ($this->option('collection')) {
            $this->createCollection($name, $namespace, $className);
        }

        return self::SUCCESS;
    }

    protected function createCollection(string $name, string $namespace, string $presenterClass): void
    {
        $collectionName = $presenterClass . 'Collection';
        $collectionPath = $this->resolveFilePath(str_replace($presenterClass, $collectionName, $name));

        $stub = <<<PHP
<?php

namespace {$namespace};

use Vinkius\\Vurb\\Presenters\\VurbResourceCollection;

class {$collectionName} extends VurbResourceCollection
{
    public \$collects = {$presenterClass}::class;

    public function systemRules(): array
    {
        return [];
    }

    public function uiBlocks(): array
    {
        return [];
    }

    public function suggestActions(): array
    {
        return [];
    }
}
PHP;

        $dir = dirname($collectionPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($collectionPath, $stub);

        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $collectionPath);
        $this->components->info("Collection created: {$relativePath}");
    }

    protected function resolveFilePath(string $name): string
    {
        $parts = explode('/', str_replace('\\', '/', $name));
        $fileName = array_pop($parts);
        $subDir = implode(DIRECTORY_SEPARATOR, $parts);

        $basePath = app_path('Vurb/Presenters');

        if ($subDir !== '') {
            return $basePath . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . $fileName . '.php';
        }

        return $basePath . DIRECTORY_SEPARATOR . $fileName . '.php';
    }

    protected function resolveNamespace(string $name): string
    {
        $parts = explode('/', str_replace('\\', '/', $name));
        array_pop($parts);

        $baseNamespace = 'App\\Vurb\\Presenters';

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
}
