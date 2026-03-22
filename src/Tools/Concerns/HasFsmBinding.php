<?php

namespace Vinkius\Vurb\Tools\Concerns;

use Vinkius\Vurb\Attributes\FsmBind;

trait HasFsmBinding
{
    /**
     * Get the FSM binding configuration from #[FsmBind] attribute.
     */
    public function getFsmBinding(): ?array
    {
        $ref = new \ReflectionClass($this);
        $attrs = $ref->getAttributes(FsmBind::class);

        if (empty($attrs)) {
            return null;
        }

        $bind = $attrs[0]->newInstance();

        return [
            'states' => $bind->states,
            'event' => $bind->event,
        ];
    }
}
