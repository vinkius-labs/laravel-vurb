<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
    public function __construct(
        public ?string $description = null,
        public mixed $example = null,
        public string|array|null $items = null,
    ) {}
}
