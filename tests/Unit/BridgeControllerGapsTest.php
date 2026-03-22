<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Vinkius\Vurb\Events\StateInvalidated;
use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;
use Vinkius\Vurb\Tests\TestCase;

class BridgeControllerGapsTest extends TestCase
{
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = config('vurb.internal_token');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('vurb.server.name', 'test-server');
        $app['config']->set('vurb.server.version', '1.0.0');
        $app['config']->set('vurb.bridge.base_url', 'http://localhost');
        $app['config']->set('vurb.bridge.prefix', '/_vurb');
        $app['config']->set('vurb.dlp.enabled', false);
        $app['config']->set('vurb.observability.events', false);
    }

    // ─── ModelNotFoundException → 404 with model/ids details ───

    public function test_execute_model_not_found_returns_404_with_details(): void
    {
        // GetMissingLead is in Crm/ with Router prefix "crm"
        // Class: GetMissingLead → snake: get_missing_lead → resolveToolName: crm.get_missing_lead
        // Route: POST /_vurb/execute/crm.get_missing_lead/handle
        $response = $this->postJson('/_vurb/execute/crm.get_missing_lead/handle', [
            'id' => 999,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['code' => 'NOT_FOUND']);
        $response->assertJsonFragment(['message' => 'Resource not found.']);
        $response->assertJsonPath('details.model', 'Lead');
        $response->assertJsonPath('details.ids', [999]);
    }

    // ─── ValidationException → 422 with errors ───

    public function test_execute_validation_exception_returns_422_with_errors(): void
    {
        // GetCustomerProfile requires 'id' (int, non-optional, non-nullable)
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'VALIDATION_ERROR']);
        $response->assertJsonFragment(['message' => 'Validation failed.']);
        $response->assertJsonStructure(['details' => ['errors']]);
    }

    // ─── Generic Throwable + debug mode off → 500 "Internal server error." ───

    public function test_execute_throwable_with_debug_off_returns_generic_message(): void
    {
        $this->app['config']->set('app.debug', false);
        $this->app['config']->set('vurb.observability.events', false);

        // ListOrders with invalid enum value causes TypeError → Throwable catch
        $response = $this->postJson('/_vurb/execute/orders.list/handle', [
            'status' => 'TOTALLY_INVALID',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(500);
        $response->assertJsonFragment(['code' => 'INTERNAL_ERROR']);
        // With debug off, message should be generic
        $response->assertJsonFragment(['message' => 'Internal server error.']);
    }

    public function test_execute_throwable_with_debug_on_returns_actual_message(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('vurb.observability.events', false);

        $response = $this->postJson('/_vurb/execute/orders.list/handle', [
            'status' => 'TOTALLY_INVALID',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(500);
        $response->assertJsonFragment(['code' => 'INTERNAL_ERROR']);
        // With debug on, actual error message is shown
        $this->assertNotSame('Internal server error.', $response->json('message'));
    }

    // ─── Events dispatched on success ───

    public function test_execute_with_events_dispatches_tool_executed(): void
    {
        $this->app['config']->set('vurb.observability.events', true);
        Event::fake([ToolExecuted::class]);

        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 42,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();

        Event::assertDispatched(ToolExecuted::class, function (ToolExecuted $e) {
            return $e->toolName === 'customers.get_profile'
                && $e->isError === false
                && $e->latencyMs > 0;
        });
    }

    // ─── Events dispatched on failure ───

    public function test_execute_with_events_dispatches_tool_failed_on_throwable(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('vurb.observability.events', true);
        Event::fake([ToolFailed::class]);

        $response = $this->postJson('/_vurb/execute/orders.list/handle', [
            'status' => 'INVALID',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(500);

        Event::assertDispatched(ToolFailed::class, function (ToolFailed $e) {
            return $e->toolName === 'orders.list'
                && $e->latencyMs > 0;
        });
    }

    // ─── FSM transition: valid ───

    public function test_fsm_transition_valid_event_returns_new_state(): void
    {
        $this->createFsmTable();

        $this->app['config']->set('vurb.fsm', [
            'id' => 'checkout_flow',
            'initial' => 'idle',
            'store' => 'database',
            'states' => [
                'idle' => ['on' => ['BEGIN' => 'cart']],
                'cart' => ['on' => ['CHECKOUT' => 'payment']],
                'payment' => ['on' => ['PAY' => 'done']],
                'done' => ['on' => []],
            ],
        ]);

        $this->app['config']->set('vurb.observability.events', true);
        Event::fake([StateInvalidated::class]);

        $response = $this->postJson('/_vurb/state/transition', [
            'session_id' => 'gap-test-session',
            'event' => 'BEGIN',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'previousState' => 'idle',
            'currentState' => 'cart',
            'event' => 'BEGIN',
        ]);

        Event::assertDispatched(StateInvalidated::class, function (StateInvalidated $e) {
            return $e->pattern === 'fsm.checkout_flow' && $e->trigger === 'BEGIN';
        });

        // Verify persistence
        $record = DB::table('vurb_fsm_states')
            ->where('session_id', 'gap-test-session')
            ->where('fsm_id', 'checkout_flow')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('cart', $record->current_state);
    }

    // ─── FSM transition: invalid event ───

    public function test_fsm_transition_invalid_event_returns_422(): void
    {
        $this->createFsmTable();

        $this->app['config']->set('vurb.fsm', [
            'id' => 'checkout_flow',
            'initial' => 'idle',
            'store' => 'database',
            'states' => [
                'idle' => ['on' => ['BEGIN' => 'cart']],
                'cart' => ['on' => ['CHECKOUT' => 'payment']],
                'payment' => ['on' => ['PAY' => 'done']],
                'done' => ['on' => []],
            ],
        ]);

        // Try to PAY while in 'idle' state
        $response = $this->postJson('/_vurb/state/transition', [
            'session_id' => 'gap-test-session-2',
            'event' => 'PAY',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'INVALID_TRANSITION']);
        $response->assertJsonFragment(['currentState' => 'idle']);
        $this->assertContains('BEGIN', $response->json('validEvents'));
    }

    // ─── FSM disabled (no config) → 400 ───

    public function test_fsm_transition_disabled_returns_400(): void
    {
        $this->app['config']->set('vurb.fsm', null);

        $response = $this->postJson('/_vurb/state/transition', [
            'session_id' => 'any',
            'event' => 'START',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['code' => 'FSM_DISABLED']);
    }

    // ─── Helper ───

    protected function createFsmTable(): void
    {
        Schema::create('vurb_fsm_states', function ($table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('fsm_id');
            $table->string('current_state');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'fsm_id']);
        });
    }
}
