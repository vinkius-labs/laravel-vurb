<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Tool
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
