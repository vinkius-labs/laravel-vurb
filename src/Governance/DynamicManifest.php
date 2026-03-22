<?php

namespace Vinkius\Vurb\Governance;

use Vinkius\Vurb\Services\ManifestCompiler;

class DynamicManifest
{
    public function __construct(
        protected ManifestCompiler $compiler,
    ) {}

    /**
     * Get the full manifest, optionally filtered by the introspection RBAC callback.
     */
    public function resolve(array $context = []): array
    {
        $manifest = $this->compiler->compile();

        if (! config('vurb.introspection.enabled', false)) {
            return $manifest;
        }

        $filter = config('vurb.introspection.filter');

        if (! is_callable($filter)) {
            return $manifest;
        }

        return $filter($manifest, $context);
    }

    /**
     * Get a manifest filtered for a specific user/role context.
     */
    public function forUser(mixed $user): array
    {
        $context = [
            'user' => $user,
            'role' => method_exists($user, 'getVurbRole') ? $user->getVurbRole() : null,
        ];

        return $this->resolve($context);
    }

    /**
     * List tool names visible to a given context.
     */
    public function visibleTools(array $context = []): array
    {
        $manifest = $this->resolve($context);
        $names = [];

        foreach ($manifest['tools'] ?? [] as $namespace => $tools) {
            foreach ($tools as $tool) {
                $names[] = $tool['name'] ?? ($namespace . '.unknown');
            }
        }

        return $names;
    }
}
