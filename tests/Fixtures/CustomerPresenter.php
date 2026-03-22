<?php

namespace Vinkius\Vurb\Tests\Fixtures;

use Vinkius\Vurb\Presenters\VurbPresenter;

/**
 * Simple test presenter for CRM leads.
 */
class CustomerPresenter extends VurbPresenter
{
    public function toArray($request = null): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'name' => $this->resource['name'] ?? null,
        ];
    }

    public function systemRules(): array
    {
        return ['Never expose raw email', 'Always use formal names'];
    }

    public function uiBlocks(): array
    {
        return [
            ['type' => 'summary', 'data' => ['label' => 'Customer']],
        ];
    }

    public function suggestActions(): array
    {
        return [
            ['tool' => 'crm.update_lead', 'reason' => 'Edit this lead'],
        ];
    }
}
