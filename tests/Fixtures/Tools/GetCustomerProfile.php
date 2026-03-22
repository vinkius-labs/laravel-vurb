<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Attributes\Cached;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Attributes\Tags;
use Vinkius\Vurb\Tools\VurbQuery;

#[Tags('crm', 'public')]
#[Cached(ttl: 60)]
class GetCustomerProfile extends VurbQuery
{
    public function description(): string
    {
        return 'Retrieve a customer profile by ID.';
    }

    public function handle(
        #[Param(description: 'Customer ID', example: 42)] int $id,
        #[Param(description: 'Include orders?')] bool $include_orders = false,
    ): array {
        return [
            'id' => $id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'include_orders' => $include_orders,
        ];
    }
}
