<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Tags
{
    public array $values;

    public function __construct(string ...$values)
    {
        $this->values = $values;
    }
}
