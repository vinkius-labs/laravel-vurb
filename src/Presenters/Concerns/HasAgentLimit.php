<?php

namespace Vinkius\Vurb\Presenters\Concerns;

use Vinkius\Vurb\Attributes\AgentLimit;

trait HasAgentLimit
{
    /**
     * Get the agent limit from the #[AgentLimit] attribute.
     */
    public function getAgentLimit(): ?array
    {
        $ref = new \ReflectionClass($this);
        $attrs = $ref->getAttributes(AgentLimit::class);

        if (empty($attrs)) {
            return null;
        }

        $limit = $attrs[0]->newInstance();

        return [
            'max' => $limit->max,
            'warningMessage' => $limit->warningMessage,
        ];
    }
}
