<?php

namespace Vinkius\Vurb\Tools;

/**
 * Query — readOnly: true. For read operations without side effects.
 */
abstract class VurbQuery extends VurbTool
{
    public function verb(): string
    {
        return 'query';
    }
}
