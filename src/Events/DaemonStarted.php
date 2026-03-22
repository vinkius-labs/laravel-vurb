<?php

namespace Vinkius\Vurb\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DaemonStarted
{
    use Dispatchable;

    public function __construct(
        public readonly ?int $pid,
        public readonly string $transport,
    ) {}
}
