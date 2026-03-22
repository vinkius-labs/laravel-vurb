<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class FsmBind
{
    public function __construct(
        public array $states = [],
        public ?string $event = null,
    ) {}
}
