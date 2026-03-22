<?php

namespace Vinkius\Vurb\Tools;

/**
 * Action — neutral. For updates, syncs, idempotent operations.
 */
abstract class VurbAction extends VurbTool
{
    public function verb(): string
    {
        return 'action';
    }
}
