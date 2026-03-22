<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tests\Fixtures\OrderStatus;
use Vinkius\Vurb\Tools\VurbQuery;

class ListOrders extends VurbQuery
{
    public function description(): string
    {
        return 'List orders by status.';
    }

    public function handle(
        OrderStatus $status,
        #[Param(description: 'Page number')] int $page = 1,
    ): array {
        return [
            'status' => $status->value,
            'page' => $page,
            'orders' => [],
        ];
    }
}
