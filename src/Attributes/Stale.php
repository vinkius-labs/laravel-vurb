<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Stale
{
    public function __construct() {}
}
