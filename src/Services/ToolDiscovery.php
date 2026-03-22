<?php

namespace Vinkius\Vurb\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Vinkius\Vurb\Attributes\Tags;
use Vinkius\Vurb\Tools\VurbRouter;
use Vinkius\Vurb\Tools\VurbTool;

class ToolDiscovery
{
    protected ?array $discovered = null;

    /**
     * Discover all VurbTool classes in the configured tools directory.
     *
     * @return array<string, array{tool: VurbTool, router: ?VurbRouter, middleware: array}>
     */
    public function discover(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $path = config('vurb.tools.path');
        $namespace = config('vurb.tools.namespace');

        if (! is_dir($path)) {
            $this->discovered = [];
            return $this->discovered;
        }

        $this->discovered = [];

        $this->scanDirectory($path, $namespace);

        return $this->discovered;
    }

    /**
     * Recursively scan a directory for VurbTool classes.
     */
    protected function scanDirectory(string $path, string $namespace, ?VurbRouter $parentRouter = null): void
    {
        $files = File::files($path);
        $directories = File::directories($path);

        // Check for Router.php in current directory
        $router = $this->findRouter($path, $namespace) ?? $parentRouter;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $file->getFilenameWithoutExtension();

            // Skip Router files
            if ($className === 'Router') {
                continue;
            }

            $fqcn = $namespace . '\\' . $className;

            if (! class_exists($fqcn)) {
                continue;
            }

            $ref = new ReflectionClass($fqcn);

            if ($ref->isAbstract() || ! $ref->isSubclassOf(VurbTool::class)) {
                continue;
            }

            $tool = app()->make($fqcn);
            $toolName = $this->resolveToolName($tool, $router);

            // Merge middleware: global → router → per-tool
            $middleware = array_merge(
                config('vurb.middleware', []),
                $router?->middleware ?? [],
                $tool->middleware,
            );

            // Merge tags from router
            $tags = $this->mergeRouterTags($tool, $router);

            $this->discovered[$toolName] = [
                'tool' => $tool,
                'class' => $fqcn,
                'router' => $router,
                'middleware' => $middleware,
                'tags' => $tags,
            ];
        }

        // Recurse into subdirectories
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $childNamespace = $namespace . '\\' . $dirName;
            $this->scanDirectory($dir, $childNamespace, $router);
        }
    }

    /**
     * Find a Router class in the given directory.
     */
    protected function findRouter(string $path, string $namespace): ?VurbRouter
    {
        $routerFile = $path . DIRECTORY_SEPARATOR . 'Router.php';

        if (! file_exists($routerFile)) {
            return null;
        }

        $fqcn = $namespace . '\\Router';

        if (! class_exists($fqcn)) {
            return null;
        }

        $ref = new ReflectionClass($fqcn);

        if (! $ref->isSubclassOf(VurbRouter::class) || $ref->isAbstract()) {
            return null;
        }

        return new $fqcn();
    }

    /**
     * Resolve the final tool name, applying router prefix if applicable.
     */
    protected function resolveToolName(VurbTool $tool, ?VurbRouter $router): string
    {
        $name = $tool->name();

        // If tool has a router and name doesn't already contain a dot from the router prefix
        if ($router !== null && ! empty($router->prefix)) {
            $actionKey = class_basename(get_class($tool));
            $actionName = Str::snake($actionKey);

            // Strip common verb prefixes for the action key
            $verbs = ['get_', 'list_', 'search_', 'create_', 'update_', 'delete_', 'remove_', 'process_', 'send_', 'cancel_', 'approve_', 'reject_', 'activate_', 'deactivate_'];
            $cleanAction = $actionName;
            foreach ($verbs as $verb) {
                if (str_starts_with($actionName, $verb)) {
                    $cleanAction = $actionName;
                    break;
                }
            }

            return $router->prefix . '.' . $cleanAction;
        }

        return $name;
    }

    /**
     * Merge tags from the router #[Tags] attribute with tool tags.
     */
    protected function mergeRouterTags(VurbTool $tool, ?VurbRouter $router): array
    {
        $tags = $tool->tags();

        // Get tags from tool's #[Tags] attribute
        $ref = new ReflectionClass($tool);
        $tagAttrs = $ref->getAttributes(Tags::class);
        if (! empty($tagAttrs)) {
            $tags = array_unique(array_merge($tags, $tagAttrs[0]->newInstance()->values));
        }

        // Get tags from router's #[Tags] attribute
        if ($router !== null) {
            $routerRef = new ReflectionClass($router);
            $routerTagAttrs = $routerRef->getAttributes(Tags::class);
            if (! empty($routerTagAttrs)) {
                $tags = array_unique(array_merge($tags, $routerTagAttrs[0]->newInstance()->values));
            }
        }

        return array_values($tags);
    }

    /**
     * Clear the discovery cache.
     */
    public function clearCache(): void
    {
        $this->discovered = null;
    }

    /**
     * Get all discovered tool names.
     */
    public function toolNames(): array
    {
        return array_keys($this->discover());
    }

    /**
     * Get a specific tool by name.
     */
    public function findTool(string $name): ?array
    {
        $tools = $this->discover();

        return $tools[$name] ?? null;
    }
}
