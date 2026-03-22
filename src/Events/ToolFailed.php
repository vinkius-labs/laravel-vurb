<?php

namespace Vinkius\Vurb\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ToolFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $toolName,
        public readonly array $input,
        public readonly string $error,
        public readonly float $latencyMs,
    ) {}
}
