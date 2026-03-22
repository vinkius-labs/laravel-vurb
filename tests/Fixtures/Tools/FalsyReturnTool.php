<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Tools\VurbQuery;

/**
 * Tool returning various falsy values for edge-case testing.
 */
class FalsyReturnTool extends VurbQuery
{
    public function description(): string
    {
        return 'Returns various falsy values for testing.';
    }

    public function handle(string $mode = 'zero'): mixed
    {
        return match ($mode) {
            'zero' => 0,
            'false' => false,
            'empty_string' => '',
            'empty_array' => [],
            'null' => null,
            default => $mode,
        };
    }
}
