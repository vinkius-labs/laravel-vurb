<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vinkius\Vurb\Fsm\FsmConfig;
use Vinkius\Vurb\Fsm\FsmStateStore;
use Vinkius\Vurb\Tests\TestCase;

class FsmStateStoreExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('vurb.fsm', [
            'id' => 'test_flow',
            'initial' => 'idle',
            'store' => 'database',
            'states' => [
                'idle' => ['on' => ['START' => 'active']],
                'active' => ['on' => ['PAY' => 'payment', 'CANCEL' => 'cancelled']],
                'payment' => ['on' => ['COMPLETE' => 'done']],
                'done' => ['on' => []],
                'cancelled' => ['on' => []],
            ],
        ]);
    }

    // ═══ Database driver: getCurrentState ═══

    public function test_get_current_state_returns_initial_when_no_record(): void
    {
        $store = $this->app->make(FsmStateStore::class);

        $state = $store->getCurrentState('new-session', 'test_flow');
        $this->assertSame('idle', $state);
    }

    public function test_get_current_state_returns_stored_state_from_database(): void
    {
        DB::table('vurb_fsm_states')->insert([
            'session_id' => 'db-sess',
            'fsm_id' => 'test_flow',
            'current_state' => 'active',
            'updated_at' => now(),
        ]);

        $store = $this->app->make(FsmStateStore::class);

        $this->assertSame('active', $store->getCurrentState('db-sess', 'test_flow'));
    }

    // ═══ Database driver: setState ═══

    public function test_set_state_inserts_new_record(): void
    {
        $store = $this->app->make(FsmStateStore::class);

        $store->setState('sess-1', 'test_flow', 'active');

        $record = DB::table('vurb_fsm_states')
            ->where('session_id', 'sess-1')
            ->where('fsm_id', 'test_flow')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('active', $record->current_state);
    }

    public function test_set_state_updates_existing_record(): void
    {
        $store = $this->app->make(FsmStateStore::class);

        $store->setState('sess-2', 'test_flow', 'active');
        $store->setState('sess-2', 'test_flow', 'payment');

        $record = DB::table('vurb_fsm_states')
            ->where('session_id', 'sess-2')
            ->where('fsm_id', 'test_flow')
            ->first();

        $this->assertSame('payment', $record->current_state);
        $this->assertSame(1, DB::table('vurb_fsm_states')
            ->where('session_id', 'sess-2')
            ->count());
    }

    public function test_set_state_with_context(): void
    {
        $store = $this->app->make(FsmStateStore::class);

        $store->setState('sess-ctx', 'test_flow', 'active', ['reason' => 'user_action']);

        $record = DB::table('vurb_fsm_states')
            ->where('session_id', 'sess-ctx')
            ->first();

        $this->assertNotNull($record->context);
        $ctx = json_decode($record->context, true);
        $this->assertSame('user_action', $ctx['reason']);
    }

    // ═══ Cache driver ═══

    public function test_cache_driver_get_returns_initial_when_empty(): void
    {
        $this->app['config']->set('vurb.fsm.store', 'cache');

        $store = $this->app->make(FsmStateStore::class);

        $this->assertSame('idle', $store->getCurrentState('cache-sess'));
    }

    public function test_cache_driver_set_and_get(): void
    {
        $this->app['config']->set('vurb.fsm.store', 'cache');

        $store = $this->app->make(FsmStateStore::class);

        $store->setState('cache-sess', null, 'active');
        $this->assertSame('active', $store->getCurrentState('cache-sess'));
    }

    public function test_cache_driver_uses_correct_cache_key(): void
    {
        $this->app['config']->set('vurb.fsm.store', 'cache');

        $store = $this->app->make(FsmStateStore::class);
        $store->setState('ck-sess', null, 'payment');

        $this->assertSame('payment', Cache::get('vurb:fsm:test_flow:ck-sess'));
    }

    // ═══ getAvailableTools ═══

    public function test_get_available_tools_returns_all_when_fsm_disabled(): void
    {
        $this->app['config']->set('vurb.fsm', null);
        $store = $this->app->make(FsmStateStore::class);

        $allTools = ['tool_a' => ['tool' => new \stdClass()], 'tool_b' => ['tool' => new \stdClass()]];
        $result = $store->getAvailableTools('any-session', $allTools);

        $this->assertCount(2, $result);
    }

    public function test_get_available_tools_filters_by_fsm_state(): void
    {
        $store = $this->app->make(FsmStateStore::class);

        // Session starts in 'idle' state
        $discovery = $this->app->make(\Vinkius\Vurb\Services\ToolDiscovery::class);
        $allTools = $discovery->discover();

        // crm.update_lead has #[FsmBind(states: ['active', 'payment'])]
        // Current state is 'idle' so it should be excluded
        $available = $store->getAvailableTools('filter-sess', $allTools);

        // update_lead should NOT be available in idle state
        $this->assertArrayNotHasKey('crm.update_lead', $available);

        // Tools without FsmBind should still be available
        $this->assertArrayHasKey('customers.get_profile', $available);
        $this->assertArrayHasKey('crm.get_lead', $available);
    }

    public function test_get_available_tools_includes_fsm_bound_tool_in_matching_state(): void
    {
        $store = $this->app->make(FsmStateStore::class);

        // Move session to 'active' state
        $store->setState('active-sess', 'test_flow', 'active');

        $discovery = $this->app->make(\Vinkius\Vurb\Services\ToolDiscovery::class);
        $allTools = $discovery->discover();

        $available = $store->getAvailableTools('active-sess', $allTools);

        // crm.update_lead binds to ['active', 'payment'] — should be available
        $this->assertArrayHasKey('crm.update_lead', $available);
    }

    // ═══ FsmConfig unit tests ═══

    public function test_fsm_config_is_enabled(): void
    {
        $config = $this->app->make(FsmConfig::class);
        $this->assertTrue($config->isEnabled());
    }

    public function test_fsm_config_is_disabled_when_null(): void
    {
        $this->app['config']->set('vurb.fsm', null);
        $config = $this->app->make(FsmConfig::class);
        $this->assertFalse($config->isEnabled());
    }

    public function test_fsm_config_get_initial_state(): void
    {
        $config = $this->app->make(FsmConfig::class);
        $this->assertSame('idle', $config->getInitialState());
    }

    public function test_fsm_config_get_fsm_id(): void
    {
        $config = $this->app->make(FsmConfig::class);
        $this->assertSame('test_flow', $config->getFsmId());
    }

    public function test_fsm_config_get_states(): void
    {
        $config = $this->app->make(FsmConfig::class);
        $states = $config->getStates();

        $this->assertArrayHasKey('idle', $states);
        $this->assertArrayHasKey('active', $states);
        $this->assertArrayHasKey('payment', $states);
    }

    public function test_fsm_config_get_valid_events(): void
    {
        $config = $this->app->make(FsmConfig::class);

        $events = $config->getValidEvents('active');
        $this->assertContains('PAY', $events);
        $this->assertContains('CANCEL', $events);
    }

    public function test_fsm_config_get_valid_events_for_terminal_state(): void
    {
        $config = $this->app->make(FsmConfig::class);

        $events = $config->getValidEvents('done');
        $this->assertEmpty($events);
    }

    public function test_fsm_config_get_next_state(): void
    {
        $config = $this->app->make(FsmConfig::class);

        $this->assertSame('active', $config->getNextState('idle', 'START'));
        $this->assertSame('payment', $config->getNextState('active', 'PAY'));
        $this->assertNull($config->getNextState('idle', 'PAY'));
    }

    public function test_fsm_config_get_store_driver(): void
    {
        $config = $this->app->make(FsmConfig::class);
        $this->assertSame('database', $config->getStoreDriver());
    }

    public function test_fsm_config_get_config_returns_full_config(): void
    {
        $config = $this->app->make(FsmConfig::class);
        $full = $config->getConfig();

        $this->assertIsArray($full);
        $this->assertArrayHasKey('id', $full);
        $this->assertArrayHasKey('initial', $full);
        $this->assertArrayHasKey('states', $full);
    }
}
