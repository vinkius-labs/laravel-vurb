<?php

namespace Vinkius\Vurb\Tools;

use Vinkius\Vurb\Attributes\Tags;

/**
 * Router — groups tools under a shared namespace, middleware, and tags.
 * Place a Router.php in a directory and all tools in that directory
 * inherit the prefix, middleware, and tags from the router.
 */
abstract class VurbRouter
{
    /**
     * Namespace prefix for all tools in this directory.
     */
    public string $prefix = '';

    /**
     * Description for the tool group.
     */
    public string $description = '';

    /**
     * Middleware applied to all tools in this group.
     */
    public array $middleware = [];
}
