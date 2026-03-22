<?php

namespace Vinkius\Vurb\StateSync;

class StateSyncConfig
{
    /**
     * Get the default state sync policy.
     */
    public function getDefault(): string
    {
        return config('vurb.state_sync.default', 'stale');
    }

    /**
     * Get all configured state sync policies.
     */
    public function getPolicies(): array
    {
        return config('vurb.state_sync.policies', []);
    }

    /**
     * Find the policy for a given tool name pattern.
     */
    public function findPolicy(string $toolName): ?array
    {
        $policies = $this->getPolicies();

        foreach ($policies as $pattern => $policy) {
            if ($this->matchesPattern($toolName, $pattern)) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * Match a tool name against a wildcard pattern.
     */
    protected function matchesPattern(string $toolName, string $pattern): bool
    {
        $regex = str_replace(['.', '*'], ['\.', '.*'], $pattern);

        return (bool) preg_match('/^' . $regex . '$/', $toolName);
    }
}
