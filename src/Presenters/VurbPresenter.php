<?php

namespace Vinkius\Vurb\Presenters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * VurbPresenter — extends Laravel's JsonResource with MVA capabilities.
 *
 * - toArray() acts as the egress firewall (what the AI sees)
 * - systemRules() provides JIT context rules per response
 * - uiBlocks() provides server-rendered visualizations
 * - suggestActions() provides HATEOAS-like action affordances
 */
abstract class VurbPresenter extends JsonResource
{
    /**
     * JIT System Rules — travel with data, not in global prompt.
     * Return null entries to conditionally exclude rules.
     *
     * @return array<string|null>
     */
    public function systemRules(): array
    {
        return [];
    }

    /**
     * Server-rendered UI blocks (ECharts, Mermaid, summaries).
     * Deterministic visualizations that the AI can reference.
     *
     * @return array<array{type: string, data: mixed}>
     */
    public function uiBlocks(): array
    {
        return [];
    }

    /**
     * Action affordances — HATEOAS for AI agents.
     * Suggests next actions based on current data state.
     *
     * @return array<array{tool: string, reason: string, args?: array}>
     */
    public function suggestActions(): array
    {
        return [];
    }
}
