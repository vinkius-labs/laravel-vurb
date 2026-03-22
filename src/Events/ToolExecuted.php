<?php

namespace Vinkius\Vurb\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ToolExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly string $toolName,
        public readonly array $input,
        public readonly float $latencyMs,
        public readonly ?string $presenterName,
        public readonly array $systemRules,
        public readonly bool $isError,
    ) {}
}
