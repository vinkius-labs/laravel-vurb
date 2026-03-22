<?php

namespace Vinkius\Vurb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Mutation
{
    public function __construct() {}
}
