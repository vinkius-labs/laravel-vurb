<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools\Crm;

use Vinkius\Vurb\Attributes\FsmBind;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbMutation;

#[FsmBind(states: ['active', 'payment'], event: 'UPDATE_LEAD')]
class UpdateLead extends VurbMutation
{
    public function description(): string
    {
        return 'Update a CRM lead.';
    }

    public function handle(
        #[Param(description: 'Lead ID')] int $id,
        #[Param(description: 'New name')] string $name,
    ): array {
        return ['id' => $id, 'name' => $name, 'updated' => true];
    }
}
