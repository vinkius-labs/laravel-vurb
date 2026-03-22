<?php

namespace Vinkius\Vurb\Tools\Concerns;

use Vinkius\Vurb\Attributes\Cached;
use Vinkius\Vurb\Attributes\Invalidates;
use Vinkius\Vurb\Attributes\Stale;

trait HasStateSync
{
    /**
     * Get the state sync configuration from attributes.
     */
    public function getStateSyncConfig(): ?array
    {
        $ref = new \ReflectionClass($this);

        // Check #[Cached]
        $cachedAttrs = $ref->getAttributes(Cached::class);
        if (! empty($cachedAttrs)) {
            $cached = $cachedAttrs[0]->newInstance();

            return $cached->ttl !== null
                ? ['policy' => 'stale-after', 'ttl' => $cached->ttl]
                : ['policy' => 'cached'];
        }

        // Check #[Stale]
        if (! empty($ref->getAttributes(Stale::class))) {
            return ['policy' => 'stale'];
        }

        return null;
    }

    /**
     * Get invalidation patterns from #[Invalidates] attribute.
     */
    public function getInvalidationPatterns(): array
    {
        $ref = new \ReflectionClass($this);
        $attrs = $ref->getAttributes(Invalidates::class);

        if (empty($attrs)) {
            return [];
        }

        return $attrs[0]->newInstance()->patterns;
    }
}
