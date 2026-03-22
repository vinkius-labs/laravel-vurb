<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools\Crm;

use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbQuery;

class SearchLeads extends VurbQuery
{
    public function description(): string
    {
        return 'Search CRM leads with various filter types.';
    }

    public function handle(
        #[Param(description: 'Search query string')]
        string $query,
        #[Param(description: 'Minimum score')]
        float $minScore = 0.0,
        #[Param(description: 'Tag filter')]
        array $tags = [],
    ): array {
        return [
            'query' => $query,
            'minScore' => $minScore,
            'tags' => $tags,
            'results' => [],
        ];
    }
}
