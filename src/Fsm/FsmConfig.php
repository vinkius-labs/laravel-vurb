<?php

namespace Vinkius\Vurb\Fsm;

class FsmConfig
{
    /**
     * Get the FSM configuration.
     */
    public function getConfig(): ?array
    {
        return config('vurb.fsm');
    }

    /**
     * Check if FSM is enabled.
     */
    public function isEnabled(): bool
    {
        return config('vurb.fsm') !== null;
    }

    /**
     * Get the initial state.
     */
    public function getInitialState(): ?string
    {
        return config('vurb.fsm.initial');
    }

    /**
     * Get the FSM ID.
     */
    public function getFsmId(): ?string
    {
        return config('vurb.fsm.id');
    }

    /**
     * Get all state definitions.
     */
    public function getStates(): array
    {
        return config('vurb.fsm.states', []);
    }

    /**
     * Get valid events for a given state.
     */
    public function getValidEvents(string $state): array
    {
        $states = $this->getStates();

        return array_keys($states[$state]['on'] ?? []);
    }

    /**
     * Get the next state for a given state + event.
     */
    public function getNextState(string $currentState, string $event): ?string
    {
        $states = $this->getStates();

        return $states[$currentState]['on'][$event] ?? null;
    }

    /**
     * Get the storage driver.
     */
    public function getStoreDriver(): string
    {
        return config('vurb.fsm.store', 'database');
    }
}
