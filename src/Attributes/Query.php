<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Query
{
    public function __construct() {}
}
