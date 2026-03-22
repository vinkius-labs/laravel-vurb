<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbQuery;

class ModelNotFoundTool extends VurbQuery
{
    public function description(): string
    {
        return 'Always throws ModelNotFoundException.';
    }

    public function handle(
        #[Param(description: 'The ID to search for')] int $id,
    ): never {
        $e = new ModelNotFoundException();
        $e->setModel('FakeModel', [$id]);
        throw $e;
    }
}
