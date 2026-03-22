<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Http\Resources\Json\JsonResource;
use Vinkius\Vurb\Attributes\AgentLimit;
use Vinkius\Vurb\Presenters\Concerns\HasAgentLimit as HasAgentLimitTrait;
use Vinkius\Vurb\Presenters\Concerns\HasSuggestions;
use Vinkius\Vurb\Presenters\Concerns\HasSystemRules;
use Vinkius\Vurb\Presenters\VurbPresenter;
use Vinkius\Vurb\Presenters\VurbResourceCollection;
use Vinkius\Vurb\Tests\TestCase;

// --- Test stub classes ---

#[AgentLimit(max: 25, warningMessage: 'Too many results')]
class PresenterWithAgentLimit
{
    use HasAgentLimitTrait;
}

class PresenterWithoutAgentLimit
{
    use HasAgentLimitTrait;
}

class StubWithSuggestions
{
    use HasSuggestions;
}

class StubWithSystemRules
{
    use HasSystemRules;
}

class ConcretePresenter extends VurbPresenter
{
    public function toArray($request): array
    {
        return ['id' => 1, 'name' => 'Test'];
    }
}

class ConcreteResourceCollection extends VurbResourceCollection
{
}

// --- Tests ---

class PresenterConcernsTest extends TestCase
{
    // --- HasAgentLimit ---

    public function test_get_agent_limit_returns_array_when_attribute_present(): void
    {
        $obj = new PresenterWithAgentLimit();
        $result = $obj->getAgentLimit();

        $this->assertIsArray($result);
        $this->assertSame(25, $result['max']);
        $this->assertSame('Too many results', $result['warningMessage']);
    }

    public function test_get_agent_limit_returns_null_when_attribute_absent(): void
    {
        $obj = new PresenterWithoutAgentLimit();
        $result = $obj->getAgentLimit();

        $this->assertNull($result);
    }

    public function test_get_agent_limit_default_warning_message_is_null(): void
    {
        // Test with default constructor (no warningMessage)
        $obj = new #[AgentLimit(max: 50)] class {
            use HasAgentLimitTrait;
        };

        $result = $obj->getAgentLimit();
        $this->assertSame(50, $result['max']);
        $this->assertNull($result['warningMessage']);
    }

    // --- HasSystemRules ---

    public function test_has_system_rules_returns_empty_array(): void
    {
        $obj = new StubWithSystemRules();
        $this->assertSame([], $obj->systemRules());
    }

    // --- HasSuggestions ---

    public function test_has_suggestions_returns_empty_array(): void
    {
        $obj = new StubWithSuggestions();
        $this->assertSame([], $obj->suggestActions());
    }

    // --- VurbPresenter ---

    public function test_vurb_presenter_system_rules_returns_empty_array(): void
    {
        $presenter = new ConcretePresenter((object) ['id' => 1]);
        $this->assertSame([], $presenter->systemRules());
    }

    public function test_vurb_presenter_ui_blocks_returns_empty_array(): void
    {
        $presenter = new ConcretePresenter((object) ['id' => 1]);
        $this->assertSame([], $presenter->uiBlocks());
    }

    public function test_vurb_presenter_suggest_actions_returns_empty_array(): void
    {
        $presenter = new ConcretePresenter((object) ['id' => 1]);
        $this->assertSame([], $presenter->suggestActions());
    }

    // --- VurbResourceCollection ---

    public function test_vurb_resource_collection_system_rules_returns_empty_array(): void
    {
        $collection = new ConcreteResourceCollection(collect([]));
        $this->assertSame([], $collection->systemRules());
    }

    public function test_vurb_resource_collection_ui_blocks_returns_empty_array(): void
    {
        $collection = new ConcreteResourceCollection(collect([]));
        $this->assertSame([], $collection->uiBlocks());
    }

    public function test_vurb_resource_collection_suggest_actions_returns_empty_array(): void
    {
        $collection = new ConcreteResourceCollection(collect([]));
        $this->assertSame([], $collection->suggestActions());
    }
}
