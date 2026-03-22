<?php

namespace Vinkius\Vurb\Models\Concerns;

/**
 * Trait HasVurbSchema — adds Vurb Model Bridge support to Eloquent Models.
 *
 * Add this trait to any Eloquent Model to make it automatically discoverable
 * by the Model Bridge and included in the Schema Manifest.
 *
 * Optional properties:
 * - $vurbDescriptions: array<string, string> — field descriptions for LLM context
 * - $vurbFillable: array<string, string[]> — per-operation fillable profiles
 */
trait HasVurbSchema
{
    /**
     * Get the Vurb schema for this model (delegated to ModelBridge).
     */
    public function toVurbSchema(): array
    {
        return app(\Vinkius\Vurb\Models\ModelBridge::class)->bridge(static::class);
    }
}
