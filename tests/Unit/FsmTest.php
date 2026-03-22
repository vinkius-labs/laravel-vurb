<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Vinkius\Vurb\Fsm\FsmConfig;
use Vinkius\Vurb\Fsm\FsmStateStore;
use Vinkius\Vurb\Tests\TestCase;

class FsmTest extends TestCase
{
    protected function defineFsmConfig(): array
    {
        return [
            'id' => 'checkout',
            'initial' => 'idle',
            'store' => 'cache',
            'states' => [
                'idle' => [
                    'on' => [
                        'START' => 'collecting',
                    ],
                ],
                'collecting' => [
                    'on' => [
                        'CONFIRM' => 'confirmed',
                        'CANCEL' => 'idle',
                    ],
                ],
                'confirmed' => [
                    'on' => [
                        'PAY' => 'paid',
                    ],
                ],
                'paid' => [
                    'on' => [],
                ],
            ],
        ];
    }

    // ─── FsmConfig ───

    public function test_fsm_config_null_when_disabled(): void
    {
        config()->set('vurb.fsm', null);
        $config = $this->app->make(FsmConfig::class);

        $this->assertNull($config->getConfig());
        $this->assertFalse($config->isEnabled());
        $this->assertNull($config->getInitialState());
        $this->assertNull($config->getFsmId());
        $this->assertEmpty($config->getStates());
    }

    public function test_fsm_config_enabled(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $config = $this->app->make(FsmConfig::class);

        $this->assertTrue($config->isEnabled());
        $this->assertSame('idle', $config->getInitialState());
        $this->assertSame('checkout', $config->getFsmId());
    }

    public function test_fsm_config_get_states(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $config = $this->app->make(FsmConfig::class);

        $states = $config->getStates();
        $this->assertArrayHasKey('idle', $states);
        $this->assertArrayHasKey('collecting', $states);
        $this->assertArrayHasKey('confirmed', $states);
        $this->assertArrayHasKey('paid', $states);
    }

    public function test_fsm_config_get_valid_events(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $config = $this->app->make(FsmConfig::class);

        $this->assertSame(['START'], $config->getValidEvents('idle'));
        $this->assertSame(['CONFIRM', 'CANCEL'], $config->getValidEvents('collecting'));
        $this->assertEmpty($config->getValidEvents('paid'));
    }

    public function test_fsm_config_get_next_state(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $config = $this->app->make(FsmConfig::class);

        $this->assertSame('collecting', $config->getNextState('idle', 'START'));
        $this->assertSame('confirmed', $config->getNextState('collecting', 'CONFIRM'));
        $this->assertSame('idle', $config->getNextState('collecting', 'CANCEL'));
        $this->assertNull($config->getNextState('idle', 'INVALID'));
    }

    public function test_fsm_config_get_valid_events_for_unknown_state(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $config = $this->app->make(FsmConfig::class);

        $this->assertEmpty($config->getValidEvents('nonexistent'));
    }

    public function test_fsm_config_get_store_driver_default(): void
    {
        config()->set('vurb.fsm', ['id' => 'test']);
        $config = $this->app->make(FsmConfig::class);

        $this->assertSame('database', $config->getStoreDriver());
    }

    public function test_fsm_config_get_store_driver_cache(): void
    {
        config()->set('vurb.fsm', array_merge($this->defineFsmConfig(), ['store' => 'cache']));
        $config = $this->app->make(FsmConfig::class);

        $this->assertSame('cache', $config->getStoreDriver());
    }

    // ─── FsmStateStore with Cache Driver ───

    public function test_state_store_cache_get_initial_state(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $store = $this->app->make(FsmStateStore::class);

        $state = $store->getCurrentState('session-1');
        $this->assertSame('idle', $state);
    }

    public function test_state_store_cache_set_and_get(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $store = $this->app->make(FsmStateStore::class);

        $store->setState('session-1', null, 'collecting');
        $state = $store->getCurrentState('session-1');
        $this->assertSame('collecting', $state);
    }

    public function test_state_store_cache_different_sessions(): void
    {
        config()->set('vurb.fsm', $this->defineFsmConfig());
        $store = $this->app->make(FsmStateStore::class);

        $store->setState('session-1', null, 'collecting');
        $store->setState('session-2', null, 'confirmed');

        $this->assertSame('collecting', $store->getCurrentState('session-1'));
        $this->assertSame('confirmed', $store->getCurrentState('session-2'));
    }

    // ─── FsmStateStore with Database Driver ───

    public function test_state_store_database_get_initial_state(): void
    {
        $fsmConfig = array_merge($this->defineFsmConfig(), ['store' => 'database']);
        config()->set('vurb.fsm', $fsmConfig);

        // Create the table
        $this->createFsmTable();

        $store = $this->app->make(FsmStateStore::class);
        $state = $store->getCurrentState('session-db-1');
        $this->assertSame('idle', $state);
    }

    public function test_state_store_database_set_and_get(): void
    {
        $fsmConfig = array_merge($this->defineFsmConfig(), ['store' => 'database']);
        config()->set('vurb.fsm', $fsmConfig);

        $this->createFsmTable();

        $store = $this->app->make(FsmStateStore::class);
        $store->setState('session-db-1', null, 'collecting', ['cart_id' => 42]);

        $state = $store->getCurrentState('session-db-1');
        $this->assertSame('collecting', $state);

        // Verify DB record
        $record = DB::table('vurb_fsm_states')
            ->where('session_id', 'session-db-1')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('collecting', $record->current_state);
        $context = json_decode($record->context, true);
        $this->assertSame(42, $context['cart_id']);
    }

    public function test_state_store_database_update_existing(): void
    {
        $fsmConfig = array_merge($this->defineFsmConfig(), ['store' => 'database']);
        config()->set('vurb.fsm', $fsmConfig);

        $this->createFsmTable();

        $store = $this->app->make(FsmStateStore::class);
        $store->setState('session-db-1', null, 'collecting');
        $store->setState('session-db-1', null, 'confirmed');

        $this->assertSame('confirmed', $store->getCurrentState('session-db-1'));

        // Only one record per session
        $count = DB::table('vurb_fsm_states')
            ->where('session_id', 'session-db-1')
            ->count();
        $this->assertSame(1, $count);
    }

    // ─── getAvailableTools ───

    public function test_get_available_tools_returns_all_when_fsm_disabled(): void
    {
        config()->set('vurb.fsm', null);
        $store = $this->app->make(FsmStateStore::class);

        $tools = ['a' => ['tool' => 'x'], 'b' => ['tool' => 'y']];
        $result = $store->getAvailableTools('session-1', $tools);

        $this->assertCount(2, $result);
    }

    protected function createFsmTable(): void
    {
        \Illuminate\Support\Facades\Schema::create('vurb_fsm_states', function ($table) {
            $table->id();
            $table->string('session_id');
            $table->string('fsm_id');
            $table->string('current_state');
            $table->json('context')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['session_id', 'fsm_id']);
        });
    }
}
