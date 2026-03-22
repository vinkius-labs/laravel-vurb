<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Tools\VurbQuery;

class FailingTool extends VurbQuery
{
    public function description(): string
    {
        return 'A tool that always throws an exception.';
    }

    public function handle(): never
    {
        throw new \RuntimeException('Something went wrong.');
    }
}
