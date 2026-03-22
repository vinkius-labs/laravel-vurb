---
name: laravel-vurb-development
description: >
  How to build production MCP servers with Laravel Vurb using the MVA (Model-View-Agent) pattern
  in PHP. Use this skill whenever writing, modifying, or reviewing Laravel Vurb code — including
  Tools, Presenters, Models, Middleware, Routers, Tests, or configuration.
  Activate when the user says "create a tool", "add a vurb endpoint", "write a presenter",
  or mentions VurbTool, VurbPresenter, HasVurbSchema, FakeVurbTester, vurb:make-tool, vurb:serve,
  or any Laravel Vurb API. This skill covers the entire package surface.
license: MIT
compatibility: Requires PHP 8.2+, Laravel 11, 12, or 13
metadata:
  author: vinkius-labs
  version: "1.0"
  tags: mcp, php, laravel, mva, vurb
---

# Laravel Vurb Development Guide

Laravel Vurb transforms any Laravel application into a production MCP Server using the **MVA (Model-View-Agent)** pattern — zero TypeScript. Eloquent Models validate, Presenters shape what the AI perceives, Tools wire it all together.

> For the complete API reference, read [llms.txt](../../../llms.txt) at the project root. This skill covers the essential patterns and rules.

## Reference Examples

Complete, runnable examples are available in `references/`. Read them for concrete implementation patterns:

| Example | Domain | Patterns Shown |
|---|---|---|
| [example-complete-crud.php](references/example-complete-crud.php) | Product Catalog | Full MVA lifecycle: Model + HasVurbSchema → Presenter → Router → Query/Mutation/Action, testing with FakeVurbTester |
| [example-presenter-patterns.php](references/example-presenter-patterns.php) | Customer Service | VurbPresenter layers: egress firewall, systemRules, uiBlocks, suggestActions, conditional rules |
| [example-middleware-auth.php](references/example-middleware-auth.php) | Multi-Tenant App | VurbMiddleware: audit trail, RBAC, rate limiting, RequirePermission, per-router middleware |
| [example-testing.php](references/example-testing.php) | E-Commerce | FakeVurbTester: MVA assertions, egress firewall validation, middleware testing, error handling |

## Project Structure

```
app/Vurb/
├── Tools/                    ← A — Tool definitions (VurbTool, VurbQuery, VurbMutation, VurbAction)
│   ├── Router.php            ← Optional top-level router
│   ├── GetCustomerProfile.php
│   ├── Crm/
│   │   ├── Router.php        ← Namespace router (prefix + middleware)
│   │   ├── GetLead.php
│   │   └── UpdateLead.php
│   └── Billing/
│       ├── Router.php
│       └── GetInvoice.php
├── Presenters/               ← V — VurbPresenter classes (extends JsonResource)
│   ├── CustomerPresenter.php
│   └── InvoicePresenter.php
├── Middleware/                ← Middleware implementations (VurbMiddleware interface)
│   └── RequireCrmAccess.php
└── Skills/                   ← SKILL.md files for AI agent capabilities
    └── laravel-vurb-development/
        └── SKILL.md
```

**Layer import rule:** Tools use Presenters, Presenters use Models, Models are Eloquent. Never import backwards.

## The Golden Rules

1. **ALWAYS use VurbPresenter for tool responses** — never return raw `toArray()`. The Presenter is the egress firewall.
2. **Use semantic verb subclasses**: `VurbQuery` = readOnly, `VurbMutation` = destructive, `VurbAction` = neutral.
3. **Typed `handle()` parameters become JSON Schema** — only primitive types (`int`, `string`, `float`, `bool`, `array`). Non-primitive types are injected from the service container.
4. **Decorate parameters with `#[Param]`** — give the LLM clear descriptions and examples. Never use ambiguous parameter names like `$q` or `$x`.
5. **One Presenter per domain entity**, reused across every tool via `#[Presenter(MyPresenter::class)]`.
6. **Use `#[Instructions]`** for anti-hallucination guidance: tell the LLM WHEN and HOW to use the tool.
7. **JIT rules go in `systemRules()`** — never put per-response rules in the global prompt.

## VurbTool — The Tool Base Class

Every tool extends `VurbTool` (or a semantic subclass) and implements `handle()`:

```php
namespace App\Vurb\Tools;

use Vinkius\Vurb\Attributes\{Param, Description, Instructions, Tags, Presenter, Cached};
use Vinkius\Vurb\Tools\VurbQuery;

#[Description('Search customers by name or email')]
#[Instructions('Only call when the user explicitly asks for a customer lookup. Never infer IDs.')]
#[Tags('crm', 'search')]
#[Cached(ttl: 120)]
#[Presenter(\App\Vurb\Presenters\CustomerPresenter::class)]
class SearchCustomers extends VurbQuery
{
    public function handle(
        #[Param(description: 'Search query — name, email, or phone', example: 'jane@acme.com')]
        string $query,

        #[Param(description: 'Maximum results to return')]
        int $limit = 20,
    ): mixed {
        return \App\Models\Customer::search($query)->take($limit)->get();
    }
}
```

### Semantic Verb Subclasses

| Class | Default Verb | Annotations |
|---|---|---|
| `VurbQuery` | `query` | `readOnly: true`, `idempotent: true` |
| `VurbMutation` | `mutation` | `destructive: true` |
| `VurbAction` | `action` | No defaults (neutral) |

### Name Inference

Tool names are auto-inferred from the class name:

| Class | Inferred Name |
|---|---|
| `GetCustomerProfile` | `customers.get_profile` |
| `CreateInvoice` | `invoices.create` |
| `ListOrders` | `orders.list` |

Override: `public function name(): string { return 'my.custom_name'; }`

### Dependency Injection

Non-primitive type hints in `handle()` are resolved from the service container:

```php
public function handle(
    int $id,                                    // ← JSON Schema parameter
    \App\Services\CrmGateway $crm,              // ← injected
    \Illuminate\Cache\Repository $cache,        // ← injected
): array {
    return $cache->remember("customer.{$id}", 60, fn () => $crm->find($id));
}
```

## VurbPresenter — Egress Firewall

The Presenter extends `JsonResource` and controls what reaches the AI:

```php
namespace App\Vurb\Presenters;

use Vinkius\Vurb\Presenters\VurbPresenter;

class CustomerPresenter extends VurbPresenter
{
    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'plan' => $this->plan,
            // 'email' deliberately omitted — PII never reaches the LLM
        ];
    }

    public function systemRules(): array
    {
        return [
            'Never reveal the customer email address.',
            'Always use the customer name in responses.',
        ];
    }

    public function uiBlocks(): array
    {
        return [
            ['type' => 'summary', 'title' => $this->name, 'subtitle' => "Plan: {$this->plan}"],
        ];
    }

    public function suggestActions(): array
    {
        return [
            ['tool' => 'customers.update', 'reason' => 'Edit customer details'],
            ['tool' => 'billing.get_invoice', 'reason' => 'View billing history'],
        ];
    }
}
```

**Critical:** Link Presenter to tool: `#[Presenter(CustomerPresenter::class)]`. Return the Eloquent model from `handle()` — the Presenter filters, enriches, and governs the response automatically.

## VurbRouter — Namespace Grouping

```php
namespace App\Vurb\Tools\Crm;

use Vinkius\Vurb\Tools\VurbRouter;

class Router extends VurbRouter
{
    public string $prefix = 'crm';
    public string $description = 'CRM operations — leads, contacts, deals';
    public array $middleware = [
        \App\Vurb\Middleware\RequireCrmAccess::class,
    ];
}
```

All tools in the `Crm/` directory inherit `crm.` prefix and `RequireCrmAccess` middleware.

## Middleware

```php
use Vinkius\Vurb\Middleware\VurbMiddleware;

class RequireCrmAccess implements VurbMiddleware
{
    public function handle(array $context, \Closure $next): mixed
    {
        if (! $context['user']?->canAccessCrm()) {
            return ['error' => true, 'code' => 'FORBIDDEN', 'message' => 'CRM access required.'];
        }
        return $next($context);
    }
}
```

Three layers merge automatically: Global (`config('vurb.middleware')`) → Router (`VurbRouter::$middleware`) → Per-tool (`VurbTool::$middleware`).

## PHP Attributes — Full Reference

| Attribute | Target | Parameters | Purpose |
|---|---|---|---|
| `#[Tool]` | Class | `name?`, `description?` | Override name/description |
| `#[Param]` | Parameter | `description?`, `example?`, `items?` | Parameter schema |
| `#[Description]` | Class/Method/Property | `value` | Description override |
| `#[Instructions]` | Class | `value` | Anti-hallucination instructions |
| `#[Tags]` | Class | `...$values` | Capability filtering tags |
| `#[Presenter]` | Class | `resource` | Link to Presenter class |
| `#[Query]` | Class | — | Mark as query verb |
| `#[Mutation]` | Class | — | Mark as mutation verb |
| `#[Action]` | Class | — | Mark as action verb |
| `#[Cached]` | Class | `ttl?` | Cache forever or stale-after |
| `#[Stale]` | Class | — | Never cache (always fresh) |
| `#[Invalidates]` | Class | `...$patterns` | Glob patterns to invalidate |
| `#[FsmBind]` | Class | `states`, `event?` | FSM state restriction |
| `#[Hidden]` | Parameter/Property | — | Exclude from LLM schema |
| `#[AgentLimit]` | Class | `max`, `warningMessage?` | Truncation guardrail |
| `#[Concurrency]` | Class | `max` | Max parallel executions |

## Eloquent Model Bridge

```php
use Vinkius\Vurb\Models\Concerns\HasVurbSchema;

class Customer extends Model
{
    use HasVurbSchema;

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['plan' => PlanEnum::class];

    public array $vurbDescriptions = [
        'name' => 'Full legal name of the customer',
        'plan' => 'Subscription tier: free, pro, or enterprise',
    ];
}
```

`$hidden` fields are auto-excluded from the Schema Manifest. `$casts` are converted to JSON Schema types. `$vurbDescriptions` add LLM-facing labels per field.

## Testing — FakeVurbTester

```php
use Vinkius\Vurb\Testing\FakeVurbTester;

$result = FakeVurbTester::for(GetCustomerProfile::class)
    ->call(['id' => 42]);

$result->assertSuccessful();
$result->assertDataHasKey('name');
$result->assertDataMissingKey('email');          // egress firewall
$result->assertHasSystemRule('Never reveal the customer email address.');
$result->assertSuggestsTool('customers.update');
```

### MvaTestResult Assertions

| Method | Validates |
|---|---|
| `assertSuccessful()` | No error |
| `assertIsError(?string $code)` | Error with optional code |
| `assertDataHasKey(string)` | Key present |
| `assertDataMissingKey(string)` | Key absent (egress) |
| `assertDataEquals(string, mixed)` | Value equality |
| `assertHasSystemRule(string)` | JIT rule present |
| `assertHasSystemRules()` | Has system rules |
| `assertHasUiBlocks()` | Has UI blocks |
| `assertHasSuggestActions()` | Has suggestions |
| `assertSuggestsTool(string)` | Tool suggested |

## Common Anti-Patterns

### ❌ Returning raw data without a Presenter

```php
// WRONG — email leaks
public function handle(int $id): array {
    return Customer::findOrFail($id)->toArray();
}

// CORRECT — Presenter strips undeclared fields
#[Presenter(CustomerPresenter::class)]
class GetCustomer extends VurbQuery {
    public function handle(int $id): Customer {
        return Customer::findOrFail($id);
    }
}
```

### ❌ Using VurbTool when you know the verb

```php
// WRONG
class GetBalance extends VurbTool {
    public function verb(): string { return 'query'; }
}

// CORRECT
class GetBalance extends VurbQuery { }
```

### ❌ Skipping #[Param] for non-obvious parameters

```php
// WRONG
public function handle(string $q, int $n = 10): array { }

// CORRECT
public function handle(
    #[Param(description: 'Search query', example: 'jane@acme.com')]
    string $query,
    #[Param(description: 'Max results to return')]
    int $limit = 10,
): array { }
```

### ❌ Putting rules in config instead of Presenter

```php
// WRONG — rules in global config
// CORRECT — rules in Presenter::systemRules(), travel WITH the data
```

## Quick Reference

| I want to... | Use |
|---|---|
| Create read-only tool | Extend `VurbQuery` |
| Create write tool | Extend `VurbMutation` |
| Create neutral tool | Extend `VurbAction` |
| Shape AI response | `VurbPresenter` + `#[Presenter]` |
| Group tools | `VurbRouter` in subdirectory |
| Add middleware | Implement `VurbMiddleware`, add to router/tool |
| Expose Eloquent model | `HasVurbSchema` trait |
| Cache results | `#[Cached]` or `#[Cached(ttl: 120)]` |
| Mark volatile | `#[Stale]` |
| Invalidate on mutation | `#[Invalidates('customers.*')]` |
| Restrict to FSM state | `#[FsmBind(states: [...], event: 'X')]` |
| Redact PII | Enable `dlp.enabled`, add regex patterns |
| Test MVA pipeline | `FakeVurbTester::for(Tool::class)->call([...])` |
| Generate lockfile | `php artisan vurb:lock` |
| Start MCP server | `php artisan vurb:serve` |
| Scaffold tool | `php artisan vurb:make-tool Name --query` |
| Scaffold presenter | `php artisan vurb:make-presenter Name` |
