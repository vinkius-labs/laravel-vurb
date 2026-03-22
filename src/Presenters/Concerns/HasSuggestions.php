<?php

namespace Vinkius\Vurb\Presenters\Concerns;

trait HasSuggestions
{
    /**
     * Override this to suggest next actions based on data state.
     */
    public function suggestActions(): array
    {
        return [];
    }
}
