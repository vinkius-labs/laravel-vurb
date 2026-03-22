<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Presenter
{
    public function __construct(
        public string $resource,
    ) {}
}
