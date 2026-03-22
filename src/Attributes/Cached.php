<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Cached
{
    public function __construct(
        public ?int $ttl = null,
    ) {}
}
