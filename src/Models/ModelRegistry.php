<?php

namespace Vinkius\Vurb\Models;

class ModelRegistry
{
    protected array $registered = [];

    protected ModelBridge $bridge;

    public function __construct()
    {
        $this->bridge = new ModelBridge();
    }

    /**
     * Register an Eloquent Model class.
     */
    public function register(string $modelClass, ?string $alias = null): void
    {
        $alias = $alias ?? class_basename($modelClass);
        $this->registered[$alias] = $modelClass;
    }

    /**
     * Auto-discover models that use the HasVurbSchema trait.
     */
    public function discoverFromModels(array $modelClasses): void
    {
        foreach ($modelClasses as $modelClass) {
            if (class_exists($modelClass) && $this->usesVurbSchema($modelClass)) {
                $this->register($modelClass);
            }
        }
    }

    /**
     * Compile all registered models into manifest format.
     */
    public function compileAll(): array
    {
        $compiled = [];

        foreach ($this->registered as $alias => $modelClass) {
            $compiled[$alias] = $this->bridge->bridge($modelClass);
        }

        return $compiled;
    }

    /**
     * Check if a model class uses the HasVurbSchema trait.
     */
    protected function usesVurbSchema(string $modelClass): bool
    {
        $traits = class_uses_recursive($modelClass);

        return in_array(Concerns\HasVurbSchema::class, $traits, true);
    }

    /**
     * Get all registered models.
     */
    public function all(): array
    {
        return $this->registered;
    }

    /**
     * Clear the registry.
     */
    public function clear(): void
    {
        $this->registered = [];
    }
}
