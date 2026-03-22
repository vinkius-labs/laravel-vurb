<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Tools\VurbMutation;

/**
 * Tool that throws various exception types for error handling tests.
 */
class MultiExceptionTool extends VurbMutation
{
    public function description(): string
    {
        return 'Throws configurable exception types.';
    }

    public function handle(string $type = 'runtime'): never
    {
        throw match ($type) {
            'runtime' => new \RuntimeException('Runtime error'),
            'logic' => new \LogicException('Logic error'),
            'type' => new \TypeError('Type error'),
            'overflow' => new \OverflowException('Overflow'),
            'domain' => new \DomainException('Domain error'),
            'empty' => new \RuntimeException(''),
            default => new \Exception('Generic error'),
        };
    }
}
