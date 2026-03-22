<?php

namespace Vinkius\Vurb\Tools\Concerns;

use Vinkius\Vurb\Attributes\Presenter as PresenterAttribute;

trait HasPresenter
{
    /**
     * Get the presenter class associated with this tool via #[Presenter] attribute.
     */
    public function getPresenterClass(): ?string
    {
        $ref = new \ReflectionClass($this);
        $attrs = $ref->getAttributes(PresenterAttribute::class);

        if (empty($attrs)) {
            return null;
        }

        return $attrs[0]->newInstance()->resource;
    }
}
