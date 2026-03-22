<?php

namespace Vinkius\Vurb\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DaemonStopped
{
    use Dispatchable;

    public function __construct(
        public readonly ?int $pid,
    ) {}
}
