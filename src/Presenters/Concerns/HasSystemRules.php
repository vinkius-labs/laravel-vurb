<?php

namespace Vinkius\Vurb\Presenters\Concerns;

trait HasSystemRules
{
    /**
     * Override this to provide JIT system rules.
     */
    public function systemRules(): array
    {
        return [];
    }
}
