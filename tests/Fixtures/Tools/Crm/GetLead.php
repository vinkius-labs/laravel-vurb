<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools\Crm;

use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Attributes\Presenter;
use Vinkius\Vurb\Tests\Fixtures\CustomerPresenter;
use Vinkius\Vurb\Tools\VurbQuery;

#[Presenter(resource: CustomerPresenter::class)]
class GetLead extends VurbQuery
{
    public function description(): string
    {
        return 'Get a CRM lead by ID.';
    }

    public function handle(
        #[Param(description: 'Lead ID')] int $id,
    ): CustomerPresenter {
        return new CustomerPresenter([
            'id' => $id,
            'name' => 'Jane Lead',
            'email' => 'jane@example.com',
        ]);
    }
}
