<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Tools\VurbQuery;

/**
 * Tool that returns null as top-level data.
 */
class NullReturningTool extends VurbQuery
{
    public function description(): string
    {
        return 'Returns null.';
    }

    public function handle(): mixed
    {
        return null;
    }
}
