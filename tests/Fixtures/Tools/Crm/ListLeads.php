<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools\Crm;

use Illuminate\Support\Collection;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbQuery;

class ListLeads extends VurbQuery
{
    public function description(): string
    {
        return 'List CRM leads.';
    }

    public function handle(
        #[Param(description: 'Max results')]
        int $limit = 5,
    ): Collection {
        return collect(range(1, $limit))->map(fn (int $i) => [
            'id' => $i,
            'name' => "Lead {$i}",
            'email' => "lead{$i}@example.com",
        ]);
    }
}
