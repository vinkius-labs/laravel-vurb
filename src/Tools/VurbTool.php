<?php

namespace Vinkius\Vurb\Tools;

use Illuminate\Support\Str;
use Vinkius\Vurb\Attributes\Description;

abstract class VurbTool
{
    /**
     * Canonical tool name (auto-inferred from class name if omitted).
     * Format: namespace.action (e.g.: customers.get_profile)
     */
    public function name(): string
    {
        return $this->inferNameFromClass();
    }

    /**
     * Description for the LLM to understand the tool's purpose.
     *
     * Reads from #[Description] attribute by default.
     * Falls back to a humanized version of the class name.
     * Override in subclass for a custom description.
     */
    public function description(): string
    {
        $ref = new \ReflectionClass(static::class);
        $attrs = $ref->getAttributes(Description::class);

        if (! empty($attrs)) {
            return $attrs[0]->newInstance()->value;
        }

        return Str::headline(class_basename(static::class));
    }

    /**
     * Additional instructions to reduce hallucination.
     * Injected as [INSTRUCTIONS] in the tool description.
     */
    public function instructions(): ?string
    {
        return null;
    }

    /**
     * Tags for capability filtering.
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * Semantic verb: query | mutation | action.
     */
    public function verb(): string
    {
        return 'action';
    }

    /**
     * Per-tool middleware classes.
     */
    public array $middleware = [];

    /**
     * Main method — receives validated arguments and returns data.
     * Supports dependency injection via Laravel Service Container.
     * Typed parameters become input schema for the LLM.
     * Non-primitive parameters are injected by the Container.
     */
    // abstract public function handle(/* ...typed */);

    /**
     * Infer a canonical name from the class name.
     * GetCustomerProfile → customers.get_profile
     * CreateInvoice → invoices.create
     */
    protected function inferNameFromClass(): string
    {
        $class = class_basename(static::class);

        // Common verb prefixes to extract
        $verbs = ['Get', 'List', 'Search', 'Create', 'Update', 'Delete', 'Remove', 'Activate', 'Deactivate', 'Process', 'Send', 'Cancel', 'Approve', 'Reject'];

        $action = null;
        $subject = $class;

        foreach ($verbs as $verb) {
            if (str_starts_with($class, $verb)) {
                $action = Str::snake($verb);
                $subject = substr($class, strlen($verb));
                break;
            }
        }

        if ($action === null) {
            // No recognized verb prefix — use full name as action under a general namespace
            return Str::snake($class);
        }

        // Pluralize subject for namespace: CustomerProfile → customers
        // Handle multi-word subjects: CustomerProfile → customer_profile → customers
        $subjectWords = Str::of($subject)->snake()->explode('_');

        // Take the first word as the namespace and pluralize it
        $namespace = Str::plural($subjectWords->first());

        // If there are more words, they become part of the action
        if ($subjectWords->count() > 1) {
            $suffix = $subjectWords->slice(1)->implode('_');
            $action = $action . '_' . $suffix;
        }

        return $namespace . '.' . $action;
    }
}
