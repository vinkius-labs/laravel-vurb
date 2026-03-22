<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbQuery;

/**
 * Tool that echoes back all input — useful for testing type coercion, encoding, and injection.
 */
class EchoTool extends VurbQuery
{
    public function description(): string
    {
        return 'Echoes back all input parameters for testing.';
    }

    public function handle(
        #[Param(description: 'Any string')] string $value = '',
        #[Param(description: 'Any integer')] int $number = 0,
        #[Param(description: 'Any array', items: 'string')] array $items = [],
    ): array {
        return [
            'value' => $value,
            'number' => $number,
            'items' => $items,
            'value_length' => mb_strlen($value),
            'value_bytes' => strlen($value),
        ];
    }
}
