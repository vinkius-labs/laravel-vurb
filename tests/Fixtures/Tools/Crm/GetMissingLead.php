<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools\Crm;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbQuery;

class GetMissingLead extends VurbQuery
{
    public function description(): string
    {
        return 'Attempts to get a lead that does not exist.';
    }

    public function handle(
        #[Param(description: 'Lead ID')]
        int $id,
    ): never {
        throw (new ModelNotFoundException())->setModel('Lead', [$id]);
    }
}
