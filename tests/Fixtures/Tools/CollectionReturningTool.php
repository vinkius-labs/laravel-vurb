<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Illuminate\Support\Collection;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbQuery;

class CollectionReturningTool extends VurbQuery
{
    public function description(): string
    {
        return 'Returns a Collection for serialization testing.';
    }

    public function handle(
        #[Param(description: 'How many items')] int $count = 2,
    ): Collection {
        return collect(range(1, $count))->map(fn ($i) => ['id' => $i, 'name' => "Item {$i}"]);
    }
}
