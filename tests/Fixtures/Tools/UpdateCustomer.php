<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Attributes\Invalidates;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbMutation;

#[Invalidates('customers.*', 'reports.*')]
class UpdateCustomer extends VurbMutation
{
    public function description(): string
    {
        return 'Update customer information.';
    }

    public function handle(
        #[Param(description: 'Customer ID')] int $id,
        #[Param(description: 'New name')] string $name,
        #[Param(description: 'New email')] ?string $email = null,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'email' => $email ?? 'unchanged@example.com',
            'updated' => true,
        ];
    }
}
