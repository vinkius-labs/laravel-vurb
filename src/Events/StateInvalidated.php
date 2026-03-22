<?php

namespace Vinkius\Vurb\Events;

use Illuminate\Foundation\Events\Dispatchable;

class StateInvalidated
{
    use Dispatchable;

    public function __construct(
        public readonly string $pattern,
        public readonly string $trigger,
    ) {}
}
