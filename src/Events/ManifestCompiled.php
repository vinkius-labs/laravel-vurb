<?php

namespace Vinkius\Vurb\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ManifestCompiled
{
    use Dispatchable;

    public function __construct(
        public readonly string $path,
        public readonly int $toolCount,
    ) {}
}
