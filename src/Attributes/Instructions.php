<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Instructions
{
    public function __construct(
        public string $value,
    ) {}
}
