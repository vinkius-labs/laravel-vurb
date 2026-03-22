<?php

namespace Vinkius\Vurb\Presenters;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * VurbResourceCollection — collection presenter with MVA capabilities.
 */
abstract class VurbResourceCollection extends ResourceCollection
{
    /**
     * JIT System Rules for collections.
     *
     * @return array<string|null>
     */
    public function systemRules(): array
    {
        return [];
    }

    /**
     * UI blocks for collection-level visualizations.
     *
     * @return array<array{type: string, data: mixed}>
     */
    public function uiBlocks(): array
    {
        return [];
    }

    /**
     * Suggest actions based on collection state.
     *
     * @return array<array{tool: string, reason: string, args?: array}>
     */
    public function suggestActions(): array
    {
        return [];
    }
}
