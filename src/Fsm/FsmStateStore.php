<?php

namespace Vinkius\Vurb\Fsm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FsmStateStore
{
    protected FsmConfig $config;

    public function __construct(FsmConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Get the current state for a session.
     */
    public function getCurrentState(string $sessionId, ?string $fsmId = null): string
    {
        $fsmId = $fsmId ?? $this->config->getFsmId();
        $driver = $this->config->getStoreDriver();

        return match ($driver) {
            'cache' => $this->getFromCache($sessionId, $fsmId),
            'database' => $this->getFromDatabase($sessionId, $fsmId),
            default => $this->getFromDatabase($sessionId, $fsmId),
        };
    }

    /**
     * Set the state for a session.
     */
    public function setState(string $sessionId, ?string $fsmId, string $state, ?array $context = null): void
    {
        $fsmId = $fsmId ?? $this->config->getFsmId();
        $driver = $this->config->getStoreDriver();

        match ($driver) {
            'cache' => $this->setInCache($sessionId, $fsmId, $state),
            'database' => $this->setInDatabase($sessionId, $fsmId, $state, $context),
            default => $this->setInDatabase($sessionId, $fsmId, $state, $context),
        };
    }

    /**
     * Get all available tools for the current state of a session.
     */
    public function getAvailableTools(string $sessionId, array $allTools): array
    {
        if (! $this->config->isEnabled()) {
            return $allTools;
        }

        $currentState = $this->getCurrentState($sessionId);
        $validEvents = $this->config->getValidEvents($currentState);

        return array_filter($allTools, function ($entry) use ($currentState) {
            $tool = $entry['tool'];
            $ref = new \ReflectionClass($tool);
            $attrs = $ref->getAttributes(\Vinkius\Vurb\Attributes\FsmBind::class);

            if (empty($attrs)) {
                return true; // Tools without FsmBind are always available
            }

            $bind = $attrs[0]->newInstance();

            return in_array($currentState, $bind->states, true);
        });
    }

    protected function getFromCache(string $sessionId, string $fsmId): string
    {
        return Cache::get("vurb:fsm:{$fsmId}:{$sessionId}", $this->config->getInitialState() ?? 'initial');
    }

    protected function setInCache(string $sessionId, string $fsmId, string $state): void
    {
        Cache::put("vurb:fsm:{$fsmId}:{$sessionId}", $state, now()->addDay());
    }

    protected function getFromDatabase(string $sessionId, string $fsmId): string
    {
        $record = DB::table('vurb_fsm_states')
            ->where('session_id', $sessionId)
            ->where('fsm_id', $fsmId)
            ->first();

        return $record?->current_state ?? $this->config->getInitialState() ?? 'initial';
    }

    protected function setInDatabase(string $sessionId, string $fsmId, string $state, ?array $context = null): void
    {
        DB::table('vurb_fsm_states')
            ->updateOrInsert(
                ['session_id' => $sessionId, 'fsm_id' => $fsmId],
                [
                    'current_state' => $state,
                    'context' => $context !== null ? json_encode($context) : null,
                    'updated_at' => now(),
                ],
            );
    }
}
