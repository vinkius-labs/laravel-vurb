<?php

namespace Vinkius\Vurb\Presenters;

use ReflectionClass;
use ReflectionMethod;
use Vinkius\Vurb\Attributes\AgentLimit;
use Vinkius\Vurb\Attributes\Presenter;

class PresenterRegistry
{
    protected array $registered = [];

    /**
     * Register a presenter class.
     */
    public function register(string $resourceClass, ?string $alias = null): void
    {
        $alias = $alias ?? class_basename($resourceClass);
        $this->registered[$alias] = $resourceClass;
    }

    /**
     * Auto-discover presenters from tools that use #[Presenter].
     */
    public function discoverFromTools(array $tools): void
    {
        foreach ($tools as $entry) {
            $tool = $entry['tool'];
            $ref = new ReflectionClass($tool);

            $attrs = $ref->getAttributes(Presenter::class);
            if (empty($attrs)) {
                continue;
            }

            $resourceClass = $attrs[0]->newInstance()->resource;
            if (class_exists($resourceClass)) {
                $this->register($resourceClass);
            }
        }
    }

    /**
     * Compile all registered presenters into manifest format.
     */
    public function compileAll(): array
    {
        $compiled = [];

        foreach ($this->registered as $alias => $resourceClass) {
            $compiled[$alias] = $this->compilePresenter($resourceClass, $alias);
        }

        return $compiled;
    }

    /**
     * Compile a single presenter class into manifest format.
     */
    protected function compilePresenter(string $resourceClass, string $alias): array
    {
        $ref = new ReflectionClass($resourceClass);

        $presenter = [
            'name' => $alias,
            'class' => $resourceClass,
        ];

        // Check if it's a VurbPresenter (has systemRules, uiBlocks, suggestActions)
        $isVurbPresenter = $ref->isSubclassOf(VurbPresenter::class);
        $presenter['isVurbPresenter'] = $isVurbPresenter;

        // Agent limit from #[AgentLimit]
        $agentLimitAttrs = $ref->getAttributes(AgentLimit::class);
        if (! empty($agentLimitAttrs)) {
            $agentLimit = $agentLimitAttrs[0]->newInstance();
            $presenter['agentLimit'] = [
                'max' => $agentLimit->max,
                'warningMessage' => $agentLimit->warningMessage,
            ];
        }

        // Schema from toArray reflection (best effort)
        $presenter['schema'] = $this->inferSchema($ref);

        // System rules marker (actual rules are runtime-dynamic)
        if ($isVurbPresenter) {
            $presenter['hasSystemRules'] = $ref->hasMethod('systemRules')
                && $ref->getMethod('systemRules')->getDeclaringClass()->getName() !== VurbPresenter::class;
            $presenter['hasUiBlocks'] = $ref->hasMethod('uiBlocks')
                && $ref->getMethod('uiBlocks')->getDeclaringClass()->getName() !== VurbPresenter::class;
            $presenter['hasSuggestActions'] = $ref->hasMethod('suggestActions')
                && $ref->getMethod('suggestActions')->getDeclaringClass()->getName() !== VurbPresenter::class;
        }

        return $presenter;
    }

    /**
     * Infer a basic schema from the toArray method.
     * This is best-effort since toArray is dynamic.
     */
    protected function inferSchema(ReflectionClass $ref): array
    {
        // We can't fully analyze the toArray() method statically.
        // The schema comes from the daemon loading it at runtime.
        return ['type' => 'object'];
    }

    /**
     * Get a registered presenter by alias.
     */
    public function get(string $alias): ?string
    {
        return $this->registered[$alias] ?? null;
    }

    /**
     * Get all registered presenters.
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
