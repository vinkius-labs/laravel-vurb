<?php

/**
 * TESTING EXAMPLE — E-Commerce MVA Assertions
 *
 * Demonstrates FakeVurbTester for in-memory MVA lifecycle testing.
 * Runs the REAL pipeline (validation → middleware → handler → presenter → egress)
 * without a daemon or HTTP — pure PHP, deterministic, instant.
 *
 * Key patterns shown:
 *   - FakeVurbTester::for() — in-memory test harness
 *   - Egress Firewall assertions — sensitive fields physically absent
 *   - JIT System Rules assertions — rules travel with data
 *   - Middleware assertions — RBAC enforcement
 *   - Error handling assertions — structured error codes
 *   - MvaTestResult chaining — fluent assertion API
 */

namespace Tests\Feature;

use Tests\TestCase;
use Vinkius\Vurb\Testing\FakeVurbTester;

// Assume these tools and presenters exist (see example-complete-crud.php):
use App\Vurb\Tools\Products\GetProduct;
use App\Vurb\Tools\Products\ListProducts;
use App\Vurb\Tools\Products\CreateProduct;
use App\Vurb\Tools\Products\UpdateProduct;
use App\Vurb\Tools\Products\DeleteProduct;


class ProductToolTest extends TestCase
{
    // ─── BASIC SUCCESS ASSERTIONS ────────────────────────────

    public function test_get_product_returns_expected_data(): void
    {
        // Create a product in the test database
        $product = \App\Models\Product::factory()->create([
            'name'        => 'Wireless Mouse',
            'price_cents' => 2999,
            'category'    => 'electronics',
            'stock'       => 15,
        ]);

        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => $product->id]);

        // Chain multiple assertions — each returns $this
        $result
            ->assertSuccessful()
            ->assertDataHasKey('id')
            ->assertDataHasKey('name')
            ->assertDataHasKey('price')
            ->assertDataHasKey('in_stock')
            ->assertDataEquals('name', 'Wireless Mouse')
            ->assertDataEquals('in_stock', true);
    }

    // ─── EGRESS FIREWALL — PII NEVER REACHES THE WIRE ───────

    public function test_presenter_strips_sensitive_fields(): void
    {
        $product = \App\Models\Product::factory()->create([
            'cost_cents'  => 1500,      // Eloquent $hidden
            'supplier_id' => 'sup_123', // Eloquent $hidden
        ]);

        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => $product->id]);

        $result
            ->assertSuccessful()
            ->assertDataMissingKey('cost_cents')    // physically absent
            ->assertDataMissingKey('supplier_id')   // physically absent
            ->assertDataMissingKey('password');      // never existed, but verify
    }

    // ─── JIT SYSTEM RULES — CONDITIONAL PER DATA STATE ──────

    public function test_low_stock_injects_warning_rule(): void
    {
        $product = \App\Models\Product::factory()->create([
            'stock' => 3,  // <= 5 triggers low stock rule
        ]);

        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => $product->id]);

        $result
            ->assertSuccessful()
            ->assertHasSystemRules()
            ->assertHasSystemRule('LOW STOCK WARNING: Suggest the user check inventory.');
    }

    public function test_normal_stock_has_no_warning_rule(): void
    {
        $product = \App\Models\Product::factory()->create([
            'stock' => 100,
        ]);

        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => $product->id]);

        $result->assertSuccessful();
        // No low stock rule — system rules are JIT, not global
    }

    // ─── UI BLOCKS — SERVER-RENDERED VISUALIZATIONS ─────────

    public function test_response_includes_ui_blocks(): void
    {
        $product = \App\Models\Product::factory()->create([
            'name' => 'Test Product',
        ]);

        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => $product->id]);

        $result
            ->assertSuccessful()
            ->assertHasUiBlocks();
    }

    // ─── ACTION AFFORDANCES — SUGGESTED NEXT ACTIONS ────────

    public function test_response_suggests_update_action(): void
    {
        $product = \App\Models\Product::factory()->create();

        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => $product->id]);

        $result
            ->assertSuccessful()
            ->assertHasSuggestActions()
            ->assertSuggestsTool('products.update');
    }

    // ─── MIDDLEWARE — RBAC ENFORCEMENT ───────────────────────

    public function test_tool_with_middleware_returns_error_when_unauthorized(): void
    {
        // Add RequirePermission middleware to the tester
        $result = FakeVurbTester::for(CreateProduct::class)
            ->withMiddleware([
                \Vinkius\Vurb\Middleware\RequirePermission::class . ':products.create',
            ])
            ->call([
                'name'        => 'Test',
                'description' => 'Test product',
                'price_cents' => 999,
                'category'    => 'other',
            ]);

        // Without a user in context, RequirePermission returns UNAUTHORIZED
        $result->assertIsError('UNAUTHORIZED');
    }

    // ─── RATE LIMITING ──────────────────────────────────────

    public function test_rate_limited_tool_returns_rate_limited_error(): void
    {
        $product = \App\Models\Product::factory()->create();

        $result = FakeVurbTester::for(GetProduct::class)
            ->withMiddleware([
                \Vinkius\Vurb\Middleware\RateLimitVurb::class,
            ])
            ->call(['id' => $product->id]);

        // First call succeeds
        $result->assertSuccessful();

        // Simulate exceeding the rate limit (in a real test, call 60+ times)
    }

    // ─── ERROR HANDLING — STRUCTURED ERROR CODES ────────────

    public function test_not_found_returns_error(): void
    {
        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => 999999]);

        $result->assertIsError();
    }

    // ─── MUTATION TESTING ───────────────────────────────────

    public function test_create_product_returns_created_data(): void
    {
        $result = FakeVurbTester::for(CreateProduct::class)
            ->call([
                'name'        => 'New Widget',
                'description' => 'A shiny new widget',
                'price_cents' => 4999,
                'category'    => 'electronics',
                'stock'       => 100,
            ]);

        $result
            ->assertSuccessful()
            ->assertDataHasKey('id')
            ->assertDataEquals('name', 'New Widget');
    }

    // ─── LATENCY MEASUREMENT ────────────────────────────────

    public function test_tool_latency_is_reasonable(): void
    {
        $product = \App\Models\Product::factory()->create();

        $result = FakeVurbTester::for(GetProduct::class)
            ->call(['id' => $product->id]);

        $result->assertSuccessful();

        // latency() returns execution time in milliseconds
        $this->assertLessThan(100, $result->latency());
    }
}
