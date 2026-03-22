<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AgentLimit
{
    public function __construct(
        public int $max = 50,
        public ?string $warningMessage = null,
    ) {}
}
