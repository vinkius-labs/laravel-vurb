<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools\Crm;

use Vinkius\Vurb\Attributes\Tags;
use Vinkius\Vurb\Tools\VurbRouter;

#[Tags('crm-router')]
class Router extends VurbRouter
{
    public string $prefix = 'crm';
    public string $description = 'CRM tool group';
    public array $middleware = [];
}
