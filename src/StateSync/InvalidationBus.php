<?php

namespace Vinkius\Vurb\StateSync;

use Vinkius\Vurb\Events\StateInvalidated;

class InvalidationBus
{
    /**
     * Emit invalidation events for the given patterns.
     */
    public function invalidate(array $patterns, string $trigger): void
    {
        foreach ($patterns as $pattern) {
            event(new StateInvalidated(
                pattern: $pattern,
                trigger: $trigger,
            ));
        }
    }
}
