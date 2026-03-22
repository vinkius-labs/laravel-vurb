<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Invalidates
{
    public array $patterns;

    public function __construct(string ...$patterns)
    {
        $this->patterns = $patterns;
    }
}
