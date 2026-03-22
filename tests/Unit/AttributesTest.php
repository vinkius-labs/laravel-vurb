<?php

namespace Vinkius\Vurb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vinkius\Vurb\Attributes\Action;
use Vinkius\Vurb\Attributes\AgentLimit;
use Vinkius\Vurb\Attributes\Cached;
use Vinkius\Vurb\Attributes\Concurrency;
use Vinkius\Vurb\Attributes\Description;
use Vinkius\Vurb\Attributes\FsmBind;
use Vinkius\Vurb\Attributes\Hidden;
use Vinkius\Vurb\Attributes\Instructions;
use Vinkius\Vurb\Attributes\Invalidates;
use Vinkius\Vurb\Attributes\Mutation;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Attributes\Presenter;
use Vinkius\Vurb\Attributes\Query;
use Vinkius\Vurb\Attributes\Stale;
use Vinkius\Vurb\Attributes\Tags;
use Vinkius\Vurb\Attributes\Tool;

class AttributesTest extends TestCase
{
    public function test_tool_attribute_with_defaults(): void
    {
        $attr = new Tool();
        $this->assertNull($attr->name);
        $this->assertNull($attr->description);
    }

    public function test_tool_attribute_with_values(): void
    {
        $attr = new Tool(name: 'my_tool', description: 'A test tool');
        $this->assertSame('my_tool', $attr->name);
        $this->assertSame('A test tool', $attr->description);
    }

    public function test_param_attribute_with_defaults(): void
    {
        $attr = new Param();
        $this->assertNull($attr->description);
        $this->assertNull($attr->example);
        $this->assertNull($attr->items);
    }

    public function test_param_attribute_with_all_values(): void
    {
        $attr = new Param(description: 'Customer ID', example: 42, items: 'string');
        $this->assertSame('Customer ID', $attr->description);
        $this->assertSame(42, $attr->example);
        $this->assertSame('string', $attr->items);
    }

    public function test_param_items_with_object_shape(): void
    {
        $items = ['field' => 'string', 'value' => 'integer'];
        $attr = new Param(items: $items);
        $this->assertSame($items, $attr->items);
    }

    public function test_cached_attribute_without_ttl(): void
    {
        $attr = new Cached();
        $this->assertNull($attr->ttl);
    }

    public function test_cached_attribute_with_ttl(): void
    {
        $attr = new Cached(ttl: 60);
        $this->assertSame(60, $attr->ttl);
    }

    public function test_stale_attribute(): void
    {
        $attr = new Stale();
        $this->assertInstanceOf(Stale::class, $attr);
    }

    public function test_invalidates_with_patterns(): void
    {
        $attr = new Invalidates('customers.*', 'reports.*');
        $this->assertSame(['customers.*', 'reports.*'], $attr->patterns);
    }

    public function test_tags_attribute(): void
    {
        $attr = new Tags('crm', 'admin', 'public');
        $this->assertSame(['crm', 'admin', 'public'], $attr->values);
    }

    public function test_description_attribute(): void
    {
        $attr = new Description('Some description');
        $this->assertSame('Some description', $attr->value);
    }

    public function test_instructions_attribute(): void
    {
        $attr = new Instructions('Always verify before running');
        $this->assertSame('Always verify before running', $attr->value);
    }

    public function test_concurrency_attribute(): void
    {
        $attr = new Concurrency(max: 5);
        $this->assertSame(5, $attr->max);
    }

    public function test_concurrency_default(): void
    {
        $attr = new Concurrency();
        $this->assertSame(1, $attr->max);
    }

    public function test_fsm_bind_attribute(): void
    {
        $attr = new FsmBind(states: ['idle', 'active'], event: 'START');
        $this->assertSame(['idle', 'active'], $attr->states);
        $this->assertSame('START', $attr->event);
    }

    public function test_presenter_attribute(): void
    {
        $attr = new Presenter(resource: 'App\\Http\\Resources\\CustomerResource');
        $this->assertSame('App\\Http\\Resources\\CustomerResource', $attr->resource);
    }

    public function test_agent_limit_defaults(): void
    {
        $attr = new AgentLimit();
        $this->assertSame(50, $attr->max);
        $this->assertNull($attr->warningMessage);
    }

    public function test_agent_limit_with_values(): void
    {
        $attr = new AgentLimit(max: 10, warningMessage: 'Truncated to 10 results');
        $this->assertSame(10, $attr->max);
        $this->assertSame('Truncated to 10 results', $attr->warningMessage);
    }

    public function test_hidden_attribute(): void
    {
        $attr = new Hidden();
        $this->assertInstanceOf(Hidden::class, $attr);
    }

    public function test_query_attribute(): void
    {
        $attr = new Query();
        $this->assertInstanceOf(Query::class, $attr);
    }

    public function test_mutation_attribute(): void
    {
        $attr = new Mutation();
        $this->assertInstanceOf(Mutation::class, $attr);
    }

    public function test_action_attribute(): void
    {
        $attr = new Action();
        $this->assertInstanceOf(Action::class, $attr);
    }
}
