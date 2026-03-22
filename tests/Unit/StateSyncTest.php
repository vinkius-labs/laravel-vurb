<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Vinkius\Vurb\Events\StateInvalidated;
use Vinkius\Vurb\StateSync\InvalidationBus;
use Vinkius\Vurb\StateSync\StateSyncConfig;
use Vinkius\Vurb\Tests\TestCase;

class StateSyncTest extends TestCase
{
    // ─── StateSyncConfig ───

    public function test_get_default_returns_stale_by_default(): void
    {
        // Don't set the key — the method's config() call provides 'stale' default
        $config = new StateSyncConfig();

        $this->assertSame('stale', $config->getDefault());
    }

    public function test_get_default_returns_configured_value(): void
    {
        config()->set('vurb.state_sync.default', 'fresh');
        $config = new StateSyncConfig();

        $this->assertSame('fresh', $config->getDefault());
    }

    public function test_get_policies_empty_by_default(): void
    {
        // Don't set the key — the method's config() call provides [] default
        $config = new StateSyncConfig();

        $this->assertEmpty($config->getPolicies());
    }

    public function test_get_policies_returns_configured(): void
    {
        $policies = [
            'billing.*' => ['ttl' => 60, 'strategy' => 'stale-while-revalidate'],
            'geo.*'     => ['ttl' => 300, 'strategy' => 'cache-first'],
        ];
        config()->set('vurb.state_sync.policies', $policies);
        $config = new StateSyncConfig();

        $this->assertSame($policies, $config->getPolicies());
    }

    public function test_find_policy_exact_match(): void
    {
        config()->set('vurb.state_sync.policies', [
            'get-customer' => ['ttl' => 30],
        ]);
        $config = new StateSyncConfig();

        $this->assertSame(['ttl' => 30], $config->findPolicy('get-customer'));
    }

    public function test_find_policy_wildcard_match(): void
    {
        config()->set('vurb.state_sync.policies', [
            'billing.*' => ['ttl' => 60],
        ]);
        $config = new StateSyncConfig();

        $this->assertSame(['ttl' => 60], $config->findPolicy('billing.invoice'));
        $this->assertSame(['ttl' => 60], $config->findPolicy('billing.payment'));
    }

    public function test_find_policy_no_match_returns_null(): void
    {
        config()->set('vurb.state_sync.policies', [
            'billing.*' => ['ttl' => 60],
        ]);
        $config = new StateSyncConfig();

        $this->assertNull($config->findPolicy('geo.lookup'));
    }

    public function test_find_policy_first_match_wins(): void
    {
        config()->set('vurb.state_sync.policies', [
            'billing.*' => ['ttl' => 60],
            'billing.invoice' => ['ttl' => 120],
        ]);
        $config = new StateSyncConfig();

        // billing.* matches first
        $this->assertSame(['ttl' => 60], $config->findPolicy('billing.invoice'));
    }

    // ─── InvalidationBus ───

    public function test_invalidate_dispatches_events(): void
    {
        Event::fake();

        $bus = new InvalidationBus();
        $bus->invalidate(['billing.*', 'geo.*'], 'update-customer');

        Event::assertDispatched(StateInvalidated::class, 2);

        Event::assertDispatched(StateInvalidated::class, function (StateInvalidated $e) {
            return $e->pattern === 'billing.*' && $e->trigger === 'update-customer';
        });

        Event::assertDispatched(StateInvalidated::class, function (StateInvalidated $e) {
            return $e->pattern === 'geo.*' && $e->trigger === 'update-customer';
        });
    }

    public function test_invalidate_empty_patterns_dispatches_nothing(): void
    {
        Event::fake();

        $bus = new InvalidationBus();
        $bus->invalidate([], 'noop');

        Event::assertNotDispatched(StateInvalidated::class);
    }
}
