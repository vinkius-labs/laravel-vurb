<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Concurrency
{
    public function __construct(
        public int $max = 1,
    ) {}
}
