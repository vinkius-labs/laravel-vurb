<?php

/**
 * PRESENTER PATTERNS EXAMPLE — Customer Service
 *
 * Demonstrates VurbPresenter patterns:
 *   - Egress firewall (toArray strips PII)
 *   - JIT system rules (conditional per data state)
 *   - Server-rendered UI blocks
 *   - Action affordances (suggestActions)
 *   - Linking Presenter to Tool via #[Presenter]
 */

// ═══════════════════════════════════════════════════════════════
// PRESENTER: CustomerPresenter — Full MVA Perception Layer
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Presenters;

use Vinkius\Vurb\Presenters\VurbPresenter;

class CustomerPresenter extends VurbPresenter
{
    /**
     * EGRESS FIREWALL — only declared fields reach the AI.
     *
     * The customer's email, phone, SSN, credit_score are all
     * properties on the Eloquent model — but they NEVER appear
     * in the response because we don't declare them here.
     */
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'plan'       => $this->plan,
            'company'    => $this->company,
            'created_at' => $this->created_at?->toDateString(),
            'is_active'  => $this->is_active,
        ];
    }

    /**
     * JIT SYSTEM RULES — travel with data, not in the global prompt.
     *
     * Rules are CONDITIONAL based on the actual data state.
     * The LLM sees different rules for different customers.
     */
    public function systemRules(): array
    {
        return array_filter([
            // Always present
            'Never reveal the customer email, phone, or SSN.',
            'Always address the customer by their full name.',

            // Conditional: enterprise gets special treatment
            $this->plan === 'enterprise'
                ? 'This is an ENTERPRISE customer. Escalate billing issues to support.'
                : null,

            // Conditional: inactive accounts
            ! $this->is_active
                ? 'WARNING: This account is DEACTIVATED. Do not process any mutations.'
                : null,

            // Conditional: new customer
            $this->created_at?->isAfter(now()->subDays(7))
                ? 'New customer (< 7 days). Offer onboarding assistance.'
                : null,
        ]);
    }

    /**
     * UI BLOCKS — server-rendered visualizations.
     * Deterministic — no hallucinated charts.
     */
    public function uiBlocks(): array
    {
        return [
            [
                'type'     => 'summary',
                'title'    => $this->name,
                'subtitle' => "{$this->plan} plan | Member since {$this->created_at?->toDateString()}",
            ],
            [
                'type' => 'badge',
                'text' => $this->is_active ? 'Active' : 'Inactive',
                'color' => $this->is_active ? 'green' : 'red',
            ],
        ];
    }

    /**
     * ACTION AFFORDANCES — HATEOAS for AI agents.
     * Suggests next actions based on actual data state.
     */
    public function suggestActions(): array
    {
        $actions = [
            ['tool' => 'customers.update', 'reason' => 'Edit customer details'],
        ];

        if ($this->is_active) {
            $actions[] = ['tool' => 'billing.get_invoice', 'reason' => 'View billing history'];
            $actions[] = ['tool' => 'support.create_ticket', 'reason' => 'Open a support ticket'];
        } else {
            $actions[] = ['tool' => 'customers.reactivate', 'reason' => 'Reactivate this account'];
        }

        return $actions;
    }
}


// ═══════════════════════════════════════════════════════════════
// PRESENTER: InvoicePresenter — Financial Data with Guardrails
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Presenters;

use Vinkius\Vurb\Presenters\VurbPresenter;

class InvoicePresenter extends VurbPresenter
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'customer_id' => $this->customer_id,
            'amount'      => '$' . number_format($this->amount_cents / 100, 2),
            'status'      => $this->status,
            'issued_at'   => $this->issued_at?->toDateString(),
            'due_at'      => $this->due_at?->toDateString(),
            'is_overdue'  => $this->due_at?->isPast() && $this->status !== 'paid',
        ];
    }

    public function systemRules(): array
    {
        return array_filter([
            'All amounts are already formatted in USD. Do not convert or recalculate.',
            $this->status === 'overdue'
                ? 'OVERDUE INVOICE: Suggest immediate payment or contact the finance team.'
                : null,
        ]);
    }

    public function uiBlocks(): array
    {
        return [
            [
                'type'     => 'summary',
                'title'    => "Invoice #{$this->id}",
                'subtitle' => "{$this->status} — {$this->toArray(null)['amount']}",
            ],
        ];
    }

    public function suggestActions(): array
    {
        return array_filter([
            $this->status === 'pending'
                ? ['tool' => 'billing.pay_invoice', 'reason' => 'Process payment']
                : null,
            $this->status === 'paid'
                ? ['tool' => 'billing.refund_invoice', 'reason' => 'Issue refund']
                : null,
            ['tool' => 'customers.get_profile', 'reason' => 'View associated customer'],
        ]);
    }
}


// ═══════════════════════════════════════════════════════════════
// TOOL: Linking Presenter via #[Presenter] Attribute
// ═══════════════════════════════════════════════════════════════

namespace App\Vurb\Tools;

use App\Models\Customer;
use Vinkius\Vurb\Attributes\{Param, Description, Presenter};
use Vinkius\Vurb\Tools\VurbQuery;

#[Description('Retrieve a customer profile by ID')]
#[Presenter(\App\Vurb\Presenters\CustomerPresenter::class)]
class GetCustomerProfile extends VurbQuery
{
    public function handle(
        #[Param(description: 'The customer ID', example: 42)]
        int $id,
    ): Customer {
        return Customer::findOrFail($id);
        // ↑ Return the Eloquent model
        // The Presenter's toArray() filters it
        // systemRules(), uiBlocks(), suggestActions() are auto-extracted
        // Response is merged: { data, systemRules, uiBlocks, suggestActions }
    }
}
