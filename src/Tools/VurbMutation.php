<?php

namespace Vinkius\Vurb\Tools;

/**
 * Mutation — destructive: true. For irreversible operations.
 */
abstract class VurbMutation extends VurbTool
{
    public function verb(): string
    {
        return 'mutation';
    }
}
