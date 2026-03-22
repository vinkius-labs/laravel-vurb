<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbQuery;

class SearchProducts extends VurbQuery
{
    public function description(): string
    {
        return 'Search products with filters.';
    }

    public function handle(
        #[Param(description: 'Search query')] string $query,
        #[Param(description: 'Filter criteria', items: ['field' => 'string', 'operator' => 'string', 'value' => 'string'])] array $filters = [],
        #[Param(description: 'Max results')] int $limit = 10,
    ): array {
        return [
            'query' => $query,
            'filters' => $filters,
            'results' => [],
            'total' => 0,
        ];
    }
}
