<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Vinkius\Vurb\Events\StateInvalidated;
use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;
use Vinkius\Vurb\Tests\TestCase;

class BridgeControllerUnitTest extends TestCase
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

    // --- Execute with RuntimeException via a properly-named tool ---

    public function test_execute_tool_throwing_runtime_exception_returns_500(): void
    {
        // MultiExceptionTool is a VurbMutation with name "multi_exception_tool" (no verb prefix match for "Multi")
        // Actually: inferNameFromClass -> "exceptions.multi" won't work... let's check.
        // MultiExceptionTool -> no recognized verb prefix -> Str::snake('MultiExceptionTool') = 'multi_exception_tool'
        // That means it has no dot, so we can't reach it via execute/{tool}/{action} as two segments.
        //
        // Instead, let's test via a tool we CAN reach: customers.update with bad data causing a validator exception.
        // Or we create a dedicated fixture. For now, test the 500 path via castValue issues.
        //
        // Actually, let's use GetCustomerProfile passing id as a string that causes no crash,
        // but let's focus on what we CAN test: the error response structure for a non-existent tool.
        // The 500 path via Throwable is already tested for MultiExceptionTool through FakeVurbTester.
        // Let's test by using debug mode:
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('vurb.observability.events', true);

        Event::fake([ToolFailed::class]);

        // Use a tool that we know doesn't throw, but we'll force an exception by binding a mock
        // Actually, the simplest approach: the echo tool name is "echo_tool" (no verb) — can't reach via route.
        // Let's test the Throwable catch by calling a valid tool but triggering an error in handle:
        // ListOrders requires an OrderStatus enum - if we pass a bad value, the enum won't match
        // and handle() receives a raw string, throwing a TypeError.
        $response = $this->postJson('/_vurb/execute/orders.list/handle', [
            'status' => 'INVALID_STATUS_VALUE',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        // This should trigger a Throwable (TypeError from BackedEnum) => 500 INTERNAL_ERROR
        $response->assertStatus(500);
        $response->assertJsonFragment(['code' => 'INTERNAL_ERROR']);

        Event::assertDispatched(ToolFailed::class, function (ToolFailed $e) {
            return $e->toolName === 'orders.list';
        });
    }

    public function test_execute_with_validation_exception_missing_required_param(): void
    {
        // GetCustomerProfile requires 'id' (int, no default)
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            // No 'id' param
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'VALIDATION_ERROR']);
        $response->assertJsonStructure(['details' => ['errors']]);
    }

    // --- FSM transition (valid) ---

    public function test_fsm_valid_transition(): void
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

        $this->app['config']->set('vurb.fsm', [
            'id' => 'order_flow',
            'initial' => 'idle',
            'store' => 'database',
            'states' => [
                'idle' => ['on' => ['START' => 'payment']],
                'payment' => ['on' => ['PAY' => 'completed']],
                'completed' => ['on' => []],
            ],
        ]);

        $this->app['config']->set('vurb.observability.events', true);
        Event::fake([StateInvalidated::class]);

        // Initial state is 'idle', send START event
        $response = $this->postJson('/_vurb/state/transition', [
            'session_id' => 'sess-fsm-1',
            'event' => 'START',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'previousState' => 'idle',
            'currentState' => 'payment',
            'event' => 'START',
        ]);

        Event::assertDispatched(StateInvalidated::class, function (StateInvalidated $e) {
            return $e->pattern === 'fsm.order_flow' && $e->trigger === 'START';
        });

        // Verify state was persisted
        $record = DB::table('vurb_fsm_states')
            ->where('session_id', 'sess-fsm-1')
            ->where('fsm_id', 'order_flow')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('payment', $record->current_state);
    }

    public function test_fsm_invalid_event_returns_422(): void
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

        $this->app['config']->set('vurb.fsm', [
            'id' => 'order_flow',
            'initial' => 'idle',
            'store' => 'database',
            'states' => [
                'idle' => ['on' => ['START' => 'payment']],
                'payment' => ['on' => ['PAY' => 'completed']],
                'completed' => ['on' => []],
            ],
        ]);

        // Send PAY while in 'idle' state (invalid)
        $response = $this->postJson('/_vurb/state/transition', [
            'session_id' => 'sess-fsm-2',
            'event' => 'PAY',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'INVALID_TRANSITION']);
        $response->assertJsonFragment(['currentState' => 'idle']);
        $this->assertContains('START', $response->json('validEvents'));
    }

    // --- Events dispatched on success ---

    public function test_execute_with_events_enabled_dispatches_tool_executed(): void
    {
        $this->app['config']->set('vurb.observability.events', true);
        Event::fake([ToolExecuted::class]);

        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        Event::assertDispatched(ToolExecuted::class, function (ToolExecuted $e) {
            return $e->toolName === 'customers.get_profile'
                && $e->isError === false;
        });
    }

    // --- DLP redaction ---

    public function test_execute_with_dlp_enabled_redacts_response(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.patterns', [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        ]);
        $this->app['config']->set('vurb.dlp.strategy', 'mask');

        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        // The email "john@example.com" should have been redacted
        $this->assertNotSame('john@example.com', $data['email']);
    }

    // --- castValue: bool/float coercion ---

    public function test_execute_tool_casts_bool_param(): void
    {
        // GetCustomerProfile has include_orders as bool
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
            'include_orders' => 1, // should be cast to bool true
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertTrue($data['include_orders']);
    }

    public function test_execute_tool_casts_int_from_string(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => '99', // string that should be cast to int
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(99, $data['id']);
    }

    // --- resolveArguments with null param ---

    public function test_execute_tool_with_nullable_param_defaults_to_null(): void
    {
        // UpdateCustomer has ?string $email = null
        $response = $this->postJson('/_vurb/execute/customers.update/handle', [
            'id' => 1,
            'name' => 'Jane',
            // email not provided → nullable default → null
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('unchanged@example.com', $data['email']); // default from UpdateCustomer handle
    }

    // --- resolveArguments with default param ---

    public function test_execute_tool_uses_default_param_value(): void
    {
        // ProcessPayment has method='card' as default
        $response = $this->postJson('/_vurb/execute/payments.process/handle', [
            'order_id' => 1,
            'amount' => 500,
            // No 'method' → should use default 'card'
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('card', $data['method']);
    }

    // --- error response structure with details ---

    public function test_error_response_contains_details_for_validation_error(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(422);
        $json = $response->json();
        $this->assertTrue($json['error']);
        $this->assertSame('VALIDATION_ERROR', $json['code']);
        $this->assertArrayHasKey('details', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('request_id', $json['meta']);
        $this->assertArrayHasKey('latency_ms', $json['meta']);
    }

    // --- Error response for 500 (debug mode off) ---

    public function test_500_error_hides_message_without_debug(): void
    {
        $this->app['config']->set('app.debug', false);

        $response = $this->postJson('/_vurb/execute/orders.list/handle', [
            'status' => 'INVALID',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(500);
        $this->assertSame('Internal server error.', $response->json('message'));
    }

    public function test_500_error_shows_message_with_debug(): void
    {
        $this->app['config']->set('app.debug', true);

        $response = $this->postJson('/_vurb/execute/orders.list/handle', [
            'status' => 'INVALID',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(500);
        // In debug mode, the actual exception message is shown
        $this->assertNotSame('Internal server error.', $response->json('message'));
    }

    // ═══ Presenter metadata in response ═══

    public function test_execute_crm_tool_returns_presenter_meta(): void
    {
        $response = $this->postJson('/_vurb/execute/crm.get_lead/handle', [
            'id' => 42,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();

        // Verify presenter data is serialized via toArray() (strips email)
        $data = $response->json('data');
        $this->assertSame(42, $data['id']);
        $this->assertSame('Jane Lead', $data['name']);
        $this->assertArrayNotHasKey('email', $data);

        // Verify presenter meta
        $this->assertArrayHasKey('systemRules', $response->json());
        $this->assertContains('Never expose raw email', $response->json('systemRules'));
        $this->assertContains('Always use formal names', $response->json('systemRules'));
        $this->assertArrayHasKey('uiBlocks', $response->json());
        $this->assertCount(1, $response->json('uiBlocks'));
        $this->assertSame('summary', $response->json('uiBlocks')[0]['type']);
        $this->assertArrayHasKey('suggestActions', $response->json());
        $this->assertSame('crm.update_lead', $response->json('suggestActions')[0]['tool']);
    }

    public function test_execute_non_presenter_tool_has_no_presenter_key(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('systemRules', $response->json());
    }

    // ═══ Middleware with parameters ═══

    public function test_execute_with_parameterised_middleware(): void
    {
        // Register the ParameterisedMiddleware as a per-tool middleware via config
        $this->app['config']->set(
            'vurb.middleware',
            [\Vinkius\Vurb\Tests\Fixtures\ParameterisedMiddleware::class . ':admin'],
        );

        // Force re-discovery
        $discovery = $this->app->make(\Vinkius\Vurb\Services\ToolDiscovery::class);
        $discovery->clearCache();

        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
    }

    // ═══ castValue branches (float, string, array) ═══

    public function test_execute_crm_search_casts_float_string_array(): void
    {
        $response = $this->postJson('/_vurb/execute/crm.search_leads/handle', [
            'query' => 'test',
            'minScore' => '0.75',
            'tags' => ['vip', 'active'],
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertSame('test', $data['query']);
        $this->assertSame(0.75, $data['minScore']);
        $this->assertSame(['vip', 'active'], $data['tags']);
    }

    // ═══ Collection serialization  ═══

    public function test_execute_crm_list_returns_collection_as_array(): void
    {
        $response = $this->postJson('/_vurb/execute/crm.list_leads/handle', ['limit' => 2], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertSame('Lead 1', $data[0]['name']);
    }

    // ═══ ModelNotFoundException  ═══

    public function test_execute_model_not_found_returns_404_with_details(): void
    {
        $response = $this->postJson('/_vurb/execute/crm.get_missing_lead/handle', ['id' => 999], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['code' => 'NOT_FOUND']);
        $response->assertJsonFragment(['message' => 'Resource not found.']);
        $details = $response->json('details');
        $this->assertSame('Lead', $details['model']);
        $this->assertContains(999, $details['ids']);
    }

    // ═══ Tool not found  ═══

    public function test_execute_nonexistent_tool_returns_404(): void
    {
        $response = $this->postJson('/_vurb/execute/nonexistent.tool/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['code' => 'NOT_FOUND']);
    }
}
