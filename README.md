<div align="center">

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://site-assets.vinkius.com/vk/vurb-for-laravel.png" >
  <img src="https://site-assets.vinkius.com/vk/vurb-for-laravel.png" style="border-radius:8px;background:#000000;padding:10px;border:1px solid #414141;"  alt="Vurb.ts">
</picture>

**Turn any Laravel app into a production MCP Server. Zero TypeScript.**<br>
PHP 8.2+ Attributes · Presenters that control what the LLM sees · PII Redaction · Eloquent Model Bridge · FSM State Gates · One command — every AI connects.

[![Packagist Version](https://img.shields.io/packagist/v/vinkius-labs/laravel-vurb?color=0ea5e9)](https://packagist.org/packages/vinkius-labs/laravel-vurb)
[![PHP Version](https://img.shields.io/badge/PHP-8.2+-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%20|%2012%20|%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![MCP Standard](https://img.shields.io/badge/MCP-Standard-purple)](https://modelcontextprotocol.io/)
[![Tests](https://img.shields.io/badge/Tests-750%20passing-brightgreen)](https://github.com/vinkius-labs/laravel-vurb/actions)
[![Coverage](https://img.shields.io/badge/Coverage-97.6%25-brightgreen)](https://github.com/vinkius-labs/laravel-vurb/actions)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![llms.txt](https://img.shields.io/badge/llms.txt-AI_Ready-8b5cf6)](llms.txt)
[![Vurb.ts](https://img.shields.io/badge/Built%20with-Vurb.ts-blueviolet?style=flat-square)](https://github.com/vinkius-labs/vurb.ts)

[Vurb.ts Docs](https://vurb.vinkius.com/) · [Quick Start](#quick-start) · [Architecture](#architecture) · [Testing](#testing--mva-assertions) · [llms.txt](llms.txt)

🤖 **Try it right now — zero install:**<br>
[▶ Open in Claude](https://claude.ai/new?q=You+MUST+use+the+Laravel+Vurb+package+%28composer%3A+vinkius-labs%2Flaravel-vurb%29.+Read+the+MANDATORY+architectural+spec+at+https%3A%2F%2Fraw.githubusercontent.com%2Fvinkius-labs%2Flaravel-vurb%2Fmain%2Fllms.txt+%E2%80%94+this+is+NOT+optional.+ALL+code+MUST+follow+the+Laravel+Vurb+MVA+pattern%3A+VurbPresenter+for+egress+control%2C+VurbTool+extending+classes+with+PHP+Attributes+%28%23%5BParam%5D%2C+%23%5BPresenter%5D%2C+%23%5BDescription%5D%2C+%23%5BInstructions%5D%29%2C+VurbRouter+for+grouping.+DO+NOT+use+raw+MCP+SDK%2C+JSON.stringify%2C+or+manual+tool+handlers.+If+ANY+tool+lacks+a+Presenter%2C+your+output+is+WRONG.+Task%3A+Build+MCP+tools+for+patient+records+with+Eloquent.+Redact+SSN+and+diagnosis+from+LLM+output.+Add+an+FSM+that+gates+discharge+tools+until+attending+physician+signs+off.) · [▶ Open in ChatGPT](https://chatgpt.com/?q=You+MUST+use+the+Laravel+Vurb+package+%28composer%3A+vinkius-labs%2Flaravel-vurb%29.+Read+the+MANDATORY+architectural+spec+at+https%3A%2F%2Fraw.githubusercontent.com%2Fvinkius-labs%2Flaravel-vurb%2Fmain%2Fllms.txt+%E2%80%94+this+is+NOT+optional.+ALL+code+MUST+follow+the+Laravel+Vurb+MVA+pattern%3A+VurbPresenter+for+egress+control%2C+VurbTool+extending+classes+with+PHP+Attributes+%28%23%5BParam%5D%2C+%23%5BPresenter%5D%2C+%23%5BDescription%5D%2C+%23%5BInstructions%5D%29%2C+VurbRouter+for+grouping.+DO+NOT+use+raw+MCP+SDK%2C+JSON.stringify%2C+or+manual+tool+handlers.+If+ANY+tool+lacks+a+Presenter%2C+your+output+is+WRONG.+Task%3A+Build+MCP+tools+for+patient+records+with+Eloquent.+Redact+SSN+and+diagnosis+from+LLM+output.+Add+an+FSM+that+gates+discharge+tools+until+attending+physician+signs+off.)

</div>

---

## Why Laravel Vurb?

Your Laravel app already has the business logic — Eloquent models, policies, middleware, jobs. Why rewrite it in TypeScript just to connect to an AI agent?

**Laravel Vurb bridges PHP to the [Model Context Protocol](https://modelcontextprotocol.io/).** Write a PHP class. Decorate with attributes. Run `php artisan vurb:serve`. Claude, Cursor, GitHub Copilot, Windsurf — every MCP-compatible client connects instantly.

```
┌─────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│  AI Agent        │ MCP   │  Vurb.ts Daemon   │ HTTP  │  Laravel App     │
│  (Cursor, Claude │◄─────►│  (auto-managed)   │◄─────►│  (your tools)    │
│   Copilot, etc.) │ stdio │  via npx          │ JSON  │  via bridge      │
└─────────────────┘       └──────────────────┘       └──────────────────┘
```

No Node.js knowledge required. No daemon configuration. The package handles everything.

---

## Zero Learning Curve — Ship a SKILL.md, Not a Tutorial

Every package you've adopted followed the same loop: read the docs, study the conventions, hit an edge case, search GitHub issues, re-read the docs. Weeks before your first PR. Your AI coding agent does the same — it hallucinates raw MCP SDK patterns or invents Laravel conventions because it has no formal contract to work from.

Laravel Vurb ships a **[SKILL.md](https://agentskills.io)** — a machine-readable architectural contract that your AI agent ingests before writing a single line. Not a tutorial. Not a "getting started guide" the LLM will paraphrase loosely. A **structural specification**: every PHP Attribute, every VurbTool method, every Presenter composition rule, every middleware signature, every name-inference convention. The agent doesn't approximate — it compiles against the spec.

The agent reads `SKILL.md` and produces:

```php
// app/Vurb/Presenters/PatientPresenter.php — generated by your AI agent
class PatientPresenter extends VurbPresenter
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'status'    => $this->status,
            'physician' => $this->attending_physician,
        ];
        // ssn, diagnosis, internal_notes — physically absent from response
    }

    public function systemRules(): array
    {
        return [
            'HIPAA: diagnosis visible in UI blocks but NEVER in conversation text.',
            'Always confirm physician identity before discharge actions.',
        ];
    }

    public function suggestActions(): array
    {
        if ($this->status === 'cleared') {
            return [['tool' => 'patients.discharge', 'reason' => 'Physician has signed off']];
        }
        return [['tool' => 'patients.sign_off', 'reason' => 'Awaiting physician sign-off']];
    }
}
```

```php
// app/Vurb/Tools/Patients/DischargePatient.php — generated by your AI agent

#[Description('Discharge a patient after physician sign-off')]
#[Instructions('NEVER call without verifying physician sign-off. Always confirm patient identity.')]
#[Presenter(PatientPresenter::class)]
#[FsmBind(states: ['cleared'], event: 'DISCHARGE')]
class DischargePatient extends VurbTool
{
    public function verb(): string { return 'mutation'; }

    public function handle(
        #[Param(description: 'Patient ID', example: 'PAT-001')]
        string $id,
    ): Patient {
        $patient = Patient::findOrFail($id);
        $patient->update(['status' => 'discharged', 'discharged_at' => now()]);
        return $patient;
    }
}
```

Correct Presenter with egress firewall — SSN and diagnosis physically stripped. FSM gating that makes `patients.discharge` invisible until physician sign-off. JIT system rules. Suggested actions computed from patient state. **First pass — no corrections.**

This works on Cursor, Claude Code, GitHub Copilot, Windsurf, Cline — any agent that can read a file. The `SKILL.md` is the single source of truth: the agent doesn't need to have been trained on Laravel Vurb, it just needs to read the spec.

> **You don't learn Laravel Vurb. You don't teach your agent Laravel Vurb.** You hand it a 400-line contract. It writes the server. You review the PR.

> 💡 The links above inject a **super prompt** that forces the AI to read [`llms.txt`](llms.txt) before writing code — guaranteeing correct MVA patterns, not hallucinated syntax.

When you install the package, the `SKILL.md` and `llms.txt` are automatically published to your project:

```bash
php artisan vurb:install
# → llms.txt copied to project root
# → .claude/skills/laravel-vurb-development/ created with SKILL.md + reference examples
```

You can also publish them individually:

```bash
php artisan vendor:publish --tag=vurb-llms      # llms.txt → project root
php artisan vendor:publish --tag=vurb-skills     # SKILL.md → .claude/skills/
```

---

## Table of Contents

- [Zero Learning Curve — Ship a SKILL.md, Not a Tutorial](#zero-learning-curve--ship-a-skillmd-not-a-tutorial)
- [Quick Start](#quick-start)
- [How It Works — The Bridge Architecture](#how-it-works--the-bridge-architecture)
- [Writing Tools](#writing-tools)
    - [Your First Tool](#your-first-tool)
    - [Name Inference](#name-inference)
    - [Semantic Verbs](#semantic-verbs)
    - [PHP Attributes — Full Control](#php-attributes--full-control)
    - [Dependency Injection in Handlers](#dependency-injection-in-handlers)
- [Presenters — Control What the LLM Sees](#presenters--control-what-the-llm-sees)
- [Routers — Group & Namespace Tools](#routers--group--namespace-tools)
- [Middleware](#middleware)
- [Eloquent Model Bridge](#eloquent-model-bridge)
- [FSM State Gate — Temporal Tool Governance](#fsm-state-gate--temporal-tool-governance)
- [DLP Redaction — PII Never Reaches the LLM](#dlp-redaction--pii-never-reaches-the-llm)
- [Governance & Lockfile](#governance--lockfile)
- [Observability — Telescope & Pulse](#observability--telescope--pulse)
- [Testing — MVA Assertions](#testing--mva-assertions)
- [Configuration Reference](#configuration-reference)
- [Artisan Commands](#artisan-commands)
- [Architecture](#architecture)
- [Ecosystem](#ecosystem)
- [Contributing](#contributing)
- [License](#license)

---

## Quick Start

```bash
composer require vinkius-labs/laravel-vurb
php artisan vurb:install
```

The installer publishes config, creates `app/Vurb/Tools/`, installs the Node.js daemon, and generates a secure internal token.

Generate your first tool:

```bash
php artisan vurb:make-tool GetCustomerProfile --query
```

```php
// app/Vurb/Tools/GetCustomerProfile.php

namespace App\Vurb\Tools;

use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbTool;

class GetCustomerProfile extends VurbTool
{
    public function description(): string
    {
        return 'Retrieve a customer profile by ID.';
    }

    public function verb(): string
    {
        return 'query';
    }

    public function handle(
        #[Param(description: 'The customer ID', example: 42)]
        int $id,
    ): array {
        $customer = \App\Models\Customer::findOrFail($id);

        return $customer->only(['id', 'name', 'plan', 'created_at']);
    }
}
```

Start the server:

```bash
php artisan vurb:serve
```

That's it. Your Laravel app is now an MCP server. Connect any AI client.

---

## How It Works — The Bridge Architecture

Laravel Vurb uses a **thin daemon bridge** — a lightweight [Vurb.ts](https://github.com/vinkius-labs/vurb.ts) process that speaks MCP natively over stdio/HTTP. The daemon reads a compiled **Schema Manifest** from your PHP tool definitions and proxies every tool call back to Laravel over HTTP.

```
                          Schema Manifest (JSON)
                        ┌───────────────────────┐
                        │  tools, presenters,    │
php artisan vurb:serve  │  models, FSM, state    │  VURB_DAEMON_READY
        │               │  sync, skills          │        │
        ▼               └───────────┬───────────┘        ▼
┌──────────────┐                    │            ┌──────────────┐
│   Laravel    │  POST /_vurb/...   │            │  Vurb.ts     │
│   Bridge     │◄───────────────────┤────────────│  Daemon      │
│   Controller │  (X-Vurb-Token)    │            │  (npx tsx)   │
└──────────────┘                    │            └──────────────┘
                                    │                    ▲
                                    │               MCP  │
                                    │                    │
                              ┌─────┴──────┐    ┌───────┴──────┐
                              │  Manifest   │    │  AI Client   │
                              │  Compiler   │    │  (Cursor,    │
                              │             │    │   Claude,    │
                              └─────────────┘    │   Copilot)   │
                                                 └──────────────┘
```

**Key design decisions:**

- **No Node.js knowledge required** — the daemon is auto-installed and managed via `npx`
- **Timing-safe token authentication** — bridge endpoints are protected with `X-Vurb-Token`
- **PHP reflection → JSON Schema** — your typed `handle()` parameters become the tool's input schema automatically
- **Zero config manifests** — the compiler reads your tool classes, attributes, and router structure

---

## Writing Tools

### Your First Tool

Every tool extends `VurbTool` and implements `handle()`:

```php
use Vinkius\Vurb\Tools\VurbTool;

class ListOrders extends VurbTool
{
    public function description(): string
    {
        return 'List recent orders for a customer.';
    }

    public function handle(int $customer_id, int $limit = 10): array
    {
        return Order::where('customer_id', $customer_id)
            ->latest()
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
```

**That's it.** The reflection engine reads your type hints:

- `int $customer_id` → `{ "type": "integer", "description": "customer_id" }` (required)
- `int $limit = 10` → `{ "type": "integer", "description": "limit" }` (optional, default: 10)

### Name Inference

Tool names are auto-inferred from the class name. No manual wiring:

| Class Name           | Inferred Name           |
| :------------------- | :---------------------- |
| `GetCustomerProfile` | `customers.get_profile` |
| `CreateInvoice`      | `invoices.create`       |
| `ListOrders`         | `orders.list`           |
| `SearchProducts`     | `products.search`       |
| `ProcessPayment`     | `payments.process`      |
| `SendNotification`   | `notifications.send`    |

Override with `public function name(): string { return 'my.custom_name'; }`.

### Semantic Verbs

Every tool declares its intent. The daemon uses this for MCP annotations:

```php
public function verb(): string
{
    return 'query';     // read-only, idempotent, cacheable
    return 'mutation';  // writes data, destructive, invalidates cache
    return 'action';    // side-effect (email, webhook), idempotent
}
```

### PHP Attributes — Full Control

Decorate tools and parameters with attributes for precise schema generation:

```php
use Vinkius\Vurb\Attributes\{Tool, Param, Description, Instructions, Tags, Presenter, Cached, Invalidates, FsmBind, Concurrency};

#[Description('Deep search across all customer records')]
#[Instructions('Only call when the user explicitly asks for a customer lookup. Never infer customer IDs.')]
#[Tags('crm', 'search')]
#[Cached(ttl: 120)]
#[Concurrency(max: 3)]
class SearchCustomers extends VurbTool
{
    public function description(): string
    {
        return 'Search customers by name or email.';
    }

    public function verb(): string
    {
        return 'query';
    }

    public function handle(
        #[Param(description: 'Search query — name, email, or phone', example: 'jane.doe@acme.com')]
        string $query,

        #[Param(description: 'Max results to return')]
        int $limit = 20,
    ): array {
        return Customer::search($query)->take($limit)->get()->toArray();
    }
}
```

| Attribute         | Target       | Purpose                                                      |
| :---------------- | :----------- | :----------------------------------------------------------- |
| `#[Tool]`         | Class        | Override `name()`, `description()`                           |
| `#[Param]`        | Parameter    | Description, example, enum items                             |
| `#[Description]`  | Class/Method | Override description string                                  |
| `#[Instructions]` | Class        | Anti-hallucination instructions injected into LLM context    |
| `#[Tags]`         | Class        | Capability filtering tags                                    |
| `#[Presenter]`    | Class        | Link to Presenter class                                      |
| `#[Cached]`       | Class        | Cache tool results (optional TTL)                            |
| `#[Stale]`        | Class        | Mark as ephemeral (always refetch)                           |
| `#[Invalidates]`  | Class        | Mutation invalidation patterns (`'customers.*'`)             |
| `#[FsmBind]`      | Class        | FSM state restriction (tool only visible in specific states) |
| `#[Concurrency]`  | Class        | Max parallel executions                                      |
| `#[AgentLimit]`   | Class        | Rate limit per agent session                                 |
| `#[Hidden]`       | Parameter    | Exclude from LLM-visible schema                              |

### Dependency Injection in Handlers

Non-primitive type hints are resolved from Laravel's service container:

```php
public function handle(
    int $id,
    \App\Services\CrmGateway $crm,        // ← injected by container
    \Illuminate\Cache\Repository $cache,   // ← injected by container
): array {
    return $cache->remember("customer.{$id}", 60, fn () => $crm->find($id));
}
```

Laravel Vurb detects that `CrmGateway` and `Repository` aren't primitive types and resolves them automatically. Only `int $id` becomes part of the input schema.

---

## Presenters — Control What the LLM Sees

**The Presenter is the most powerful concept in Vurb.** It governs what data the AI receives, what rules it must follow, and what actions it can suggest — without trusting the LLM to self-govern.

```php
// app/Vurb/Presenters/CustomerPresenter.php

namespace App\Vurb\Presenters;

use Vinkius\Vurb\Presenters\VurbPresenter;

class CustomerPresenter extends VurbPresenter
{
    public function toArray($request): array
    {
        // Only these fields reach the LLM — email is stripped
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'plan' => $this->plan,
        ];
    }

    public function systemRules(): array
    {
        return [
            'Never reveal the customer email address.',
            'Always use the customer name in responses.',
            'For billing questions, suggest the billing.get_invoice tool.',
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

Link it to a tool:

```php
use Vinkius\Vurb\Attributes\Presenter;

#[Presenter(CustomerPresenter::class)]
class GetCustomerProfile extends VurbTool
{
    // handle() returns the Customer model
    // Presenter filters, enriches, and governs the response
}
```

**What each method controls:**

| Method             | What It Does                                                            | MCP Equivalent       |
| :----------------- | :---------------------------------------------------------------------- | :------------------- |
| `toArray()`        | **Egress firewall** — only these fields reach the LLM                   | `content[].text`     |
| `systemRules()`    | **JIT context injection** — rules the LLM must follow for this response | Prepended to content |
| `uiBlocks()`       | **Server-rendered UI** — charts, summaries, tables rendered client-side | Appended to content  |
| `suggestActions()` | **HATEOAS for AI** — what the agent should do next                      | Appended to content  |

---

## Routers — Group & Namespace Tools

Group tools by domain with a `Router.php` in the directory:

```
app/Vurb/Tools/
├── Crm/
│   ├── Router.php           ← namespace + middleware for all CRM tools
│   ├── GetLead.php
│   ├── UpdateLead.php
│   └── ListLeads.php
├── Billing/
│   ├── Router.php
│   ├── GetInvoice.php
│   └── ProcessRefund.php
└── GetCustomerProfile.php   ← top-level (auto-inferred namespace)
```

```php
// app/Vurb/Tools/Crm/Router.php

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

Every tool in `Crm/` is automatically:

- Prefixed with `crm.` (e.g., `crm.get_lead`, `crm.update_lead`)
- Wrapped with `RequireCrmAccess` middleware
- Grouped in the manifest under the `crm` namespace

---

## Middleware

Middleware runs **before** tool execution — authentication, rate limiting, audit logging, input validation:

```php
use Vinkius\Vurb\Middleware\VurbMiddleware;

class AuditTrail implements VurbMiddleware
{
    public function handle(array $context, \Closure $next): mixed
    {
        $start = hrtime(true);
        $result = $next($context);
        $latency = (hrtime(true) - $start) / 1e6;

        logger()->channel('audit')->info('Tool executed', [
            'tool'    => $context['tool'],
            'user_id' => $context['user']?->id,
            'latency' => round($latency, 2),
        ]);

        return $result;
    }
}
```

**Three middleware layers, merged automatically:**

| Layer        | Scope              | Config                      |
| :----------- | :----------------- | :-------------------------- |
| **Global**   | All tools          | `config('vurb.middleware')` |
| **Router**   | Tools in directory | `Router::$middleware`       |
| **Per-tool** | Single tool        | `VurbTool::$middleware`     |

**Built-in middleware:**

| Class               | Purpose                                              |
| :------------------ | :--------------------------------------------------- |
| `AuditTrail`        | Logs tool execution with latency, user, error status |
| `RateLimitVurb`     | 60 calls/minute per tool+user (configurable)         |
| `RequirePermission` | Laravel Gate authorization with variadic permissions |

---

## Eloquent Model Bridge

Expose Eloquent models to the MCP Schema Manifest so AI agents understand your domain:

```php
// app/Models/Customer.php

use Vinkius\Vurb\Models\HasVurbSchema;

class Customer extends Model
{
    use HasVurbSchema;

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'plan' => PlanEnum::class,
        'email_verified_at' => 'datetime',
    ];

    public array $vurbDescriptions = [
        'name' => 'Full legal name of the customer',
        'plan' => 'Subscription tier: free, pro, or enterprise',
    ];
}
```

The `ModelRegistry` auto-compiles this into the manifest — including cast types, enums, hidden fields, and descriptions. The AI agent knows your domain schema before executing any tool.

---

## FSM State Gate — Temporal Tool Governance

Some tools should only be callable in specific workflow states. An FSM state gate makes tools **invisible** until the workflow reaches the right state:

```php
// config/vurb.php
'fsm' => [
    'id'      => 'order_flow',
    'initial' => 'draft',
    'store'   => 'cache',
    'states'  => [
        'draft'      => ['on' => ['CONFIRM' => 'confirmed']],
        'confirmed'  => ['on' => ['PAY' => 'paid', 'CANCEL' => 'cancelled']],
        'paid'       => ['on' => ['SHIP' => 'shipped']],
        'shipped'    => ['on' => ['DELIVER' => 'delivered']],
        'delivered'  => [],
        'cancelled'  => [],
    ],
],
```

```php
use Vinkius\Vurb\Attributes\FsmBind;

#[FsmBind(states: ['confirmed'], event: 'PAY')]
class ProcessPayment extends VurbTool
{
    // Only visible when order_flow is in 'confirmed' state
    // Triggers PAY event on execution → transitions to 'paid'
}
```

The AI cannot call `ProcessPayment` until the order is confirmed. Not through prompt injection. Not through hallucination. The tool simply does not exist in the manifest until the FSM reaches the right state.

---

## DLP Redaction — PII Never Reaches the LLM

Configure regex patterns and the redaction engine strips sensitive data from **every** tool response:

```php
// config/vurb.php
'dlp' => [
    'enabled'  => true,
    'strategy' => 'mask',       // 'mask', 'remove', or 'hash'
    'patterns' => [
        '/\b\d{3}-\d{2}-\d{4}\b/' => 'SSN',          // Social Security Number
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => 'credit_card',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z]{2,}\b/i' => 'email',
    ],
],
```

Even if a tool developer forgets to filter in `toArray()`, the DLP engine catches it. Defense in depth — Presenter egress filtering + DLP pattern matching.

---

## Governance & Lockfile

### Contract Compiler

Every tool has a surface contract — input schema + behavior annotations. The `ContractCompiler` generates SHA-256 digests:

```bash
php artisan vurb:lock
```

Generates `vurb.lock` with per-tool digests. Gate your CI pipeline:

```bash
php artisan vurb:lock --check
# Exit code 0 = contracts unchanged
# Exit code 1 = schema drift detected → review required
```

### Dynamic Manifest — RBAC Filtering

Different users see different tools:

```php
// config/vurb.php
'introspection' => [
    'enabled' => true,
    'filter'  => function (array $manifest, array $context) {
        $role = $context['role'] ?? 'viewer';

        if ($role !== 'admin') {
            unset($manifest['tools']['billing']);
        }

        return $manifest;
    },
],
```

---

## Observability — Telescope & Pulse

Zero configuration. If Laravel Telescope or Pulse is installed, tool executions are recorded automatically.

| Integration   | What's Recorded                                                                                        | Config Key                |
| :------------ | :----------------------------------------------------------------------------------------------------- | :------------------------ |
| **Telescope** | Tool name, input, latency, presenter, system rules, errors                                             | `observability.telescope` |
| **Pulse**     | Tool execution count, avg latency per tool                                                             | `observability.pulse`     |
| **Events**    | `ToolExecuted`, `ToolFailed`, `DaemonStarted`, `DaemonStopped`, `ManifestCompiled`, `StateInvalidated` | `observability.events`    |

---

## Testing — MVA Assertions

Laravel Vurb ships a purpose-built testing framework that executes the full tool pipeline in-process — no daemon, no HTTP:

```php
use Vinkius\Vurb\Testing\FakeVurbTester;

class CustomerToolTest extends TestCase
{
    public function test_get_customer_returns_profile_without_email(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 42]);

        $result
            ->assertSuccessful()
            ->assertDataHasKey('name')
            ->assertDataHasKey('plan')
            ->assertDataMissingKey('email')           // egress firewall test
            ->assertDataEquals('name', 'Jane Doe')
            ->assertHasSystemRule('Never reveal the customer email address.')
            ->assertHasUiBlock('summary')
            ->assertHasSuggestedAction('customers.update');
    }

    public function test_tool_requires_crm_permission(): void
    {
        $result = FakeVurbTester::for(GetLead::class)
            ->withMiddleware([RequirePermission::class . ':crm.read'])
            ->call(['id' => 1]);

        $result->assertIsError('UNAUTHORIZED');
    }
}
```

### Available Assertions

| Method                                | Validates                                                           |
| :------------------------------------ | :------------------------------------------------------------------ |
| `assertSuccessful()`                  | No error occurred                                                   |
| `assertIsError(?string $code)`        | Error with optional code (`VALIDATION_ERROR`, `RATE_LIMITED`, etc.) |
| `assertDataHasKey(string)`            | Response contains key                                               |
| `assertDataMissingKey(string)`        | **Egress firewall** — sensitive key NOT in response                 |
| `assertDataEquals(string, mixed)`     | Value equality                                                      |
| `assertHasSystemRule(string)`         | JIT rule present                                                    |
| `assertHasUiBlock(string, ?callable)` | UI block exists (with optional deep check)                          |
| `assertHasSuggestedAction(string)`    | Affordance present                                                  |
| `latency()`                           | Execution time in ms                                                |

---

## Configuration Reference

<details>
<summary><strong>Full <code>config/vurb.php</code></strong></summary>

| Key                       | Type        | Default                                  | Description                             |
| :------------------------ | :---------- | :--------------------------------------- | :-------------------------------------- |
| `server.name`             | string      | `APP_NAME`                               | MCP server name                         |
| `server.version`          | string      | `'1.0.0'`                                | Semantic version                        |
| `server.description`      | string      | `'MCP Server powered by Laravel Vurb'`   | Human description                       |
| `internal_token`          | string      | `env('VURB_INTERNAL_TOKEN')`             | Bridge auth token                       |
| `tools.path`              | string      | `app_path('Vurb/Tools')`                 | Tools directory                         |
| `tools.namespace`         | string      | `'App\\Vurb\\Tools'`                     | Tools namespace                         |
| `exposition`              | enum        | `'flat'`                                 | `'flat'` or `'grouped'`                 |
| `transport`               | string      | `'stdio'`                                | `'stdio'`, `'sse'`, `'streamable-http'` |
| `daemon.manifest_path`    | string      | `storage_path('app/vurb/manifest.json')` | Manifest output path                    |
| `daemon.node_path`        | string      | auto-detect                              | Custom node binary                      |
| `daemon.npx_path`         | string      | auto-detect                              | Custom npx binary                       |
| `bridge.base_url`         | url         | `'http://127.0.0.1:8000'`                | Laravel app URL                         |
| `bridge.prefix`           | string      | `'/_vurb'`                               | Route prefix                            |
| `bridge.timeout`          | int         | `30`                                     | Request timeout (seconds)               |
| `middleware`              | array       | `[]`                                     | Global middleware                       |
| `state_sync.default`      | string      | `'stale'`                                | Default cache policy                    |
| `state_sync.policies`     | array       | `[]`                                     | Per-pattern overrides                   |
| `fsm`                     | array\|null | `null`                                   | FSM configuration                       |
| `introspection.enabled`   | bool        | `false`                                  | RBAC manifest filtering                 |
| `introspection.filter`    | callable    | `null`                                   | Filter callback                         |
| `dlp.enabled`             | bool        | `false`                                  | PII redaction                           |
| `dlp.patterns`            | array       | `[]`                                     | Regex patterns                          |
| `dlp.strategy`            | string      | `'mask'`                                 | `'mask'`, `'remove'`, `'hash'`          |
| `observability.telescope` | bool        | `true`                                   | Telescope integration                   |
| `observability.pulse`     | bool        | `true`                                   | Pulse integration                       |
| `observability.events`    | bool        | `true`                                   | Dispatch events                         |

</details>

---

## Artisan Commands

| Command                      | Description                                                                                    |
| :--------------------------- | :--------------------------------------------------------------------------------------------- |
| `vurb:install`               | Install package — publish config, create directories, install npm dependencies, generate token |
| `vurb:serve`                 | Start the MCP daemon — compile manifest, launch bridge, listen for connections                 |
| `vurb:make-tool {name}`      | Generate a tool class (`--query`, `--mutation`, `--action`, `--router`)                        |
| `vurb:make-presenter {name}` | Generate a Presenter class (`--collection`)                                                    |
| `vurb:manifest`              | Compile and display the Schema Manifest (`--json`, `--write`)                                  |
| `vurb:inspect`               | Inspect tools, schemas, and demo payloads (`--tool=`, `--schema`, `--demo`)                    |
| `vurb:lock`                  | Generate `vurb.lock` for CI governance (`--check`)                                             |
| `vurb:health`                | Check daemon, Node.js, bridge, and tools health status                                         |

---

## Architecture

### Bridge Protocol

The daemon communicates with Laravel over HTTP at `/_vurb/*` endpoints:

| Endpoint                           | Method | Purpose              |
| :--------------------------------- | :----- | :------------------- |
| `/_vurb/execute/{toolName}/handle` | POST   | Execute a tool       |
| `/_vurb/schema/refresh`            | POST   | Recompile manifest   |
| `/_vurb/state/transition`          | POST   | FSM state transition |
| `/_vurb/health`                    | GET    | Health check         |

All endpoints are protected by `ValidateVurbToken` middleware using timing-safe hash comparison.

### Response Format

```json
{
    "data": { "id": 42, "name": "Jane Doe", "plan": "enterprise" },
    "meta": {
        "request_id": "a1b2c3",
        "latency_ms": 12.34,
        "tool": "customers.get_profile"
    },
    "systemRules": ["Never reveal the customer email address."],
    "uiBlocks": [{ "type": "summary", "title": "Jane Doe" }],
    "suggestActions": [{ "tool": "customers.update", "reason": "Edit details" }]
}
```

### Schema Manifest

The manifest is the contract between PHP and the daemon. Auto-compiled from your codebase:

```json
{
  "version": "1.0",
  "server": { "name": "my-app", "version": "1.0.0", "description": "..." },
  "bridge": { "baseUrl": "http://127.0.0.1:8000", "prefix": "/_vurb", "token": "..." },
  "tools": {
    "customers": [
      {
        "name": "customers.get_profile",
        "description": "Retrieve a customer profile by ID.",
        "inputSchema": {
          "type": "object",
          "properties": { "id": { "type": "integer", "description": "The customer ID" } },
          "required": ["id"]
        },
        "annotations": { "verb": "query", "readOnly": true, "presenter": "CustomerPresenter" },
        "middleware": ["App\\Vurb\\Middleware\\AuditTrail"],
        "tags": ["crm", "public"]
      }
    ],
    "billing": [...]
  },
  "presenters": { ... },
  "models": { ... },
  "stateSync": { "default": "stale", "policies": { ... } },
  "fsm": null,
  "skills": []
}
```

### Facade

```php
use Vinkius\Vurb\Facades\Vurb;

Vurb::discover();           // All discovered tools
Vurb::compileManifest();    // Compiled manifest array
Vurb::isHealthy();          // Health check
Vurb::discovery();          // ToolDiscovery instance
Vurb::compiler();           // ManifestCompiler instance
Vurb::daemon();             // DaemonManager instance
Vurb::presenters();         // PresenterRegistry instance
Vurb::models();             // ModelRegistry instance
```

---

## Ecosystem

Laravel Vurb is built on top of [**Vurb.ts**](https://github.com/vinkius-labs/vurb.ts) — the full-featured MCP framework for TypeScript. The daemon bridge connects your PHP business logic to the Vurb.ts runtime.

| Package                                                | Description                                           |
| :----------------------------------------------------- | :---------------------------------------------------- |
| [**vurb.ts**](https://github.com/vinkius-labs/vurb.ts) | The Express.js for MCP Servers — TypeScript framework |
| **laravel-vurb**                                       | This package — PHP bridge for Laravel                 |

### Compatible AI Clients

Any MCP-compatible client connects to your Laravel app:

- **Cursor** — IDE with MCP support
- **Claude Desktop** — Anthropic's desktop client
- **Claude Code** — CLI agent
- **GitHub Copilot** — VS Code agent mode
- **Windsurf** — Codeium's IDE
- **Cline** — VS Code extension

---

## Requirements

| Requirement | Version             |
| :---------- | :------------------ |
| PHP         | 8.2+                |
| Laravel     | 11, 12, or 13       |
| Node.js     | 18+ (auto-detected) |

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## License

Laravel Vurb is open-sourced software licensed under the [MIT License](LICENSE).

<div align="center">
<br>
<strong>Built by <a href="https://vinkius.com">Vinkius</a></strong>
<br>
<sub>Transform your Laravel app into an AI-ready MCP server.</sub>
</div>
