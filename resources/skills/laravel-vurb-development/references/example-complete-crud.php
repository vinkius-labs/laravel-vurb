<?php

/**
 * COMPLETE CRUD EXAMPLE — Product Catalog
 *
 * Demonstrates the full MVA lifecycle in Laravel Vurb:
 *   Model (Eloquent + HasVurbSchema) → Presenter (VurbPresenter) → Router → Query/Mutation/Action
 *
 * In production, each class lives in its own file:
 *   app/Models/Product.php
 *   app/Vurb/Presenters/ProductPresenter.php
 *   app/Vurb/Tools/Products/Router.php
 *   app/Vurb/Tools/Products/ListProducts.php
 *   app/Vurb/Tools/Products/GetProduct.php
 *   app/Vurb/Tools/Products/CreateProduct.php
 *   app/Vurb/Tools/Products/UpdateProduct.php
 *   app/Vurb/Tools/Products/DeleteProduct.php
 */

// ═══════════════════════════════════════════════════════════════
// STEP 1: THE MODEL — Eloquent + HasVurbSchema (the "M")
// ═══════════════════════════════════════════════════════════════

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Vinkius\Vurb\Models\Concerns\HasVurbSchema;

class Product extends Model
{
    use HasVurbSchema;

    protected $fillable = ['name', 'description', 'price_cents', 'category', 'stock'];

    protected $hidden = ['cost_cents', 'supplier_id']; // Never exposed to LLM

    protected $casts = [
        'price_cents' => 'integer',
        'stock'       => 'integer',
        'category'    => CategoryEnum::class,
    ];

    /**
     * Field descriptions for the LLM.
     * These become part of the Schema Manifest.
     */
    public array $vurbDescriptions = [
        'name'        => 'Product display name',
        'price_cents' => 'CRITICAL: Price in CENTS. Divide by 100 for display.',
        'category'    => 'Product category: electronics, clothing, food, books, other',
        'stock'       => 'Current inventory count',
    ];
}


// ═══════════════════════════════════════════════════════════════
// STEP 2: THE PRESENTER — Egress Firewall & Perception Layer (the "V")
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Presenters;

use Vinkius\Vurb\Presenters\VurbPresenter;

class ProductPresenter extends VurbPresenter
{
    /**
     * Egress firewall — only declared fields reach the AI.
     * cost_cents, supplier_id are Eloquent $hidden — but we also
     * explicitly control what we expose here.
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => '$' . number_format($this->price_cents / 100, 2),
            'category'    => $this->category,
            'stock'       => $this->stock,
            'in_stock'    => $this->stock > 0,
        ];
    }

    /**
     * JIT system rules — travel with the data, not in the global prompt.
     */
    public function systemRules(): array
    {
        return array_filter([
            'Always display prices with the $ symbol and two decimals.',
            'Never reveal cost_cents or supplier information.',
            $this->stock <= 5 ? 'LOW STOCK WARNING: Suggest the user check inventory.' : null,
        ]);
    }

    /**
     * Server-rendered UI blocks.
     */
    public function uiBlocks(): array
    {
        return [
            [
                'type' => 'summary',
                'title' => $this->name,
                'subtitle' => "Category: {$this->category} | Stock: {$this->stock}",
            ],
        ];
    }

    /**
     * Action affordances — HATEOAS for AI agents.
     */
    public function suggestActions(): array
    {
        return array_filter([
            ['tool' => 'products.update', 'reason' => 'Edit product details'],
            $this->stock <= 5
                ? ['tool' => 'products.restock', 'reason' => 'Restock — inventory is low']
                : null,
        ]);
    }
}


// ═══════════════════════════════════════════════════════════════
// STEP 3: THE ROUTER — Namespace + Shared Middleware (grouping)
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Tools\Products;

use Vinkius\Vurb\Tools\VurbRouter;

class Router extends VurbRouter
{
    public string $prefix = 'products';
    public string $description = 'Product catalog operations';
    public array $middleware = [
        \App\Vurb\Middleware\AuditTrail::class,
    ];
}


// ═══════════════════════════════════════════════════════════════
// STEP 4: TOOLS — Query / Mutation / Action (the "A")
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Tools\Products;

use App\Models\Product;
use Vinkius\Vurb\Attributes\{Param, Description, Instructions, Tags, Presenter, Cached, Stale, Invalidates};
use Vinkius\Vurb\Tools\{VurbQuery, VurbMutation, VurbAction};

// ─── QUERY: List Products ────────────────────────────────────

#[Description('List products with optional filtering')]
#[Tags('catalog', 'public')]
#[Cached(ttl: 60)]
#[Presenter(\App\Vurb\Presenters\ProductPresenter::class)]
class ListProducts extends VurbQuery
{
    public function handle(
        #[Param(description: 'Filter by category', items: ['electronics', 'clothing', 'food', 'books', 'other'])]
        ?string $category = null,

        #[Param(description: 'Maximum results to return')]
        int $limit = 20,
    ): mixed {
        $query = Product::query();

        if ($category) {
            $query->where('category', $category);
        }

        return $query->latest()->limit($limit)->get();
    }
}

// ─── QUERY: Get Single Product ───────────────────────────────

#[Description('Get a product by ID')]
#[Tags('catalog', 'public')]
#[Cached(ttl: 120)]
#[Presenter(\App\Vurb\Presenters\ProductPresenter::class)]
class GetProduct extends VurbQuery
{
    public function handle(
        #[Param(description: 'The product ID', example: 42)]
        int $id,
    ): Product {
        return Product::findOrFail($id);
        // ↑ Return the model — Presenter filters and enriches automatically
    }
}

// ─── MUTATION: Create Product ────────────────────────────────

#[Description('Create a new product in the catalog')]
#[Instructions('Only use when the user explicitly asks to create a product. Confirm the price before creating.')]
#[Tags('catalog', 'admin')]
#[Invalidates('products.*')]
#[Presenter(\App\Vurb\Presenters\ProductPresenter::class)]
class CreateProduct extends VurbMutation
{
    public function handle(
        #[Param(description: 'Product name', example: 'Wireless Mouse')]
        string $name,

        #[Param(description: 'Product description')]
        string $description,

        #[Param(description: 'Price in cents (e.g., 2999 = $29.99)', example: 2999)]
        int $price_cents,

        #[Param(description: 'Product category', items: ['electronics', 'clothing', 'food', 'books', 'other'])]
        string $category,

        #[Param(description: 'Initial stock count')]
        int $stock = 0,
    ): Product {
        return Product::create(compact('name', 'description', 'price_cents', 'category', 'stock'));
    }
}

// ─── ACTION: Update Product ──────────────────────────────────

#[Description('Update an existing product')]
#[Tags('catalog', 'admin')]
#[Invalidates('products.*')]
#[Presenter(\App\Vurb\Presenters\ProductPresenter::class)]
class UpdateProduct extends VurbAction
{
    public function handle(
        #[Param(description: 'Product ID to update', example: 42)]
        int $id,

        #[Param(description: 'New product name')]
        ?string $name = null,

        #[Param(description: 'New description')]
        ?string $description = null,

        #[Param(description: 'New price in cents')]
        ?int $price_cents = null,

        #[Param(description: 'New stock count')]
        ?int $stock = null,
    ): Product {
        $product = Product::findOrFail($id);

        $product->update(array_filter(
            compact('name', 'description', 'price_cents', 'stock'),
            fn ($v) => $v !== null,
        ));

        return $product->fresh();
    }
}

// ─── MUTATION: Delete Product ────────────────────────────────

#[Description('Permanently delete a product from the catalog')]
#[Instructions('DESTRUCTIVE: Always confirm with the user before deleting. This cannot be undone.')]
#[Tags('catalog', 'admin')]
#[Invalidates('products.*')]
class DeleteProduct extends VurbMutation
{
    public function handle(
        #[Param(description: 'Product ID to delete', example: 42)]
        int $id,
    ): array {
        $product = Product::findOrFail($id);
        $product->delete();

        return ['deleted' => true, 'id' => $id, 'name' => $product->name];
    }
}
