<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Attributes\AgentLimit;
use Vinkius\Vurb\Attributes\Presenter;
use Vinkius\Vurb\Presenters\PresenterRegistry;
use Vinkius\Vurb\Presenters\VurbPresenter;
use Vinkius\Vurb\Tests\TestCase;
use Vinkius\Vurb\Tools\VurbQuery;

// --- Test fixtures inline ---

#[AgentLimit(max: 25, warningMessage: 'Limit reached')]
class TestVurbPresenter extends VurbPresenter
{
    public function toArray($request = null): array
    {
        return ['id' => $this->resource['id'], 'name' => $this->resource['name']];
    }

    public function systemRules(): array
    {
        return ['Always be polite', 'Never share PII'];
    }

    public function uiBlocks(): array
    {
        return [['type' => 'chart', 'data' => ['series' => []]]];
    }

    public function suggestActions(): array
    {
        return [['tool' => 'orders.list', 'reason' => 'View recent orders']];
    }
}

#[Presenter(resource: TestVurbPresenter::class)]
class PresenterFixtureTool extends VurbQuery
{
    public function description(): string
    {
        return 'Tool with presenter attached';
    }

    public function name(): string
    {
        return 'test.presenter_fixture';
    }

    public function handle(): array
    {
        return [];
    }
}

class PresenterRegistryExtendedTest extends TestCase
{
    protected PresenterRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PresenterRegistry();
    }

    // --- compilePresenter with VurbPresenter subclass ---

    public function test_compile_presenter_with_vurb_presenter_subclass(): void
    {
        $this->registry->register(TestVurbPresenter::class, 'TestVurbPresenter');
        $compiled = $this->registry->compileAll();

        $this->assertArrayHasKey('TestVurbPresenter', $compiled);
        $entry = $compiled['TestVurbPresenter'];

        $this->assertTrue($entry['isVurbPresenter']);
        $this->assertTrue($entry['hasSystemRules']);
        $this->assertTrue($entry['hasUiBlocks']);
        $this->assertTrue($entry['hasSuggestActions']);
        $this->assertSame('TestVurbPresenter', $entry['name']);
        $this->assertSame(TestVurbPresenter::class, $entry['class']);
    }

    // --- compilePresenter with AgentLimit ---

    public function test_compile_presenter_with_agent_limit(): void
    {
        $this->registry->register(TestVurbPresenter::class, 'TestVurbPresenter');
        $compiled = $this->registry->compileAll();

        $entry = $compiled['TestVurbPresenter'];
        $this->assertArrayHasKey('agentLimit', $entry);
        $this->assertSame(25, $entry['agentLimit']['max']);
        $this->assertSame('Limit reached', $entry['agentLimit']['warningMessage']);
    }

    // --- compilePresenter schema inference ---

    public function test_compile_presenter_has_schema(): void
    {
        $this->registry->register(TestVurbPresenter::class, 'TestVurbPresenter');
        $compiled = $this->registry->compileAll();

        $entry = $compiled['TestVurbPresenter'];
        $this->assertArrayHasKey('schema', $entry);
        $this->assertSame('object', $entry['schema']['type']);
    }

    // --- discoverFromTools with #[Presenter] ---

    public function test_discover_from_tools_with_presenter_attribute(): void
    {
        $tool = $this->app->make(PresenterFixtureTool::class);
        $tools = [
            'test.presenter_fixture' => [
                'tool' => $tool,
                'class' => PresenterFixtureTool::class,
                'router' => null,
                'middleware' => [],
                'tags' => [],
            ],
        ];

        $this->registry->discoverFromTools($tools);

        $all = $this->registry->all();
        $this->assertNotEmpty($all);
        // The presenter was registered with class_basename as alias
        $this->assertArrayHasKey('TestVurbPresenter', $all);
    }

    // --- discoverFromTools ignores tools without #[Presenter] ---

    public function test_discover_from_tools_ignores_tools_without_presenter(): void
    {
        $tool = $this->app->make(\Vinkius\Vurb\Tests\Fixtures\Tools\EchoTool::class);
        $tools = [
            'echo_tool' => [
                'tool' => $tool,
                'class' => \Vinkius\Vurb\Tests\Fixtures\Tools\EchoTool::class,
                'router' => null,
                'middleware' => [],
                'tags' => [],
            ],
        ];

        $this->registry->discoverFromTools($tools);

        $all = $this->registry->all();
        $this->assertEmpty($all);
    }

    // --- register + get ---

    public function test_register_and_get(): void
    {
        $this->registry->register(TestVurbPresenter::class, 'MyPresenter');

        $this->assertSame(TestVurbPresenter::class, $this->registry->get('MyPresenter'));
        $this->assertNull($this->registry->get('NonExistent'));
    }

    // --- clear ---

    public function test_clear_removes_all_registered(): void
    {
        $this->registry->register(TestVurbPresenter::class, 'Foo');
        $this->registry->clear();

        $this->assertEmpty($this->registry->all());
    }

    // --- compile with presenter that doesn't override systemRules ---

    public function test_compile_base_vurb_presenter_reports_no_overrides(): void
    {
        // Create a minimal VurbPresenter that doesn't override the methods
        $minimalPresenter = new class(null) extends VurbPresenter {
            public function toArray($request = null): array
            {
                return [];
            }
        };
        $className = get_class($minimalPresenter);

        $this->registry->register($className, 'MinimalPresenter');
        $compiled = $this->registry->compileAll();

        $entry = $compiled['MinimalPresenter'];
        $this->assertTrue($entry['isVurbPresenter']);
        // These should be false since the anonymous class declares them in the base only
        $this->assertFalse($entry['hasSystemRules']);
        $this->assertFalse($entry['hasUiBlocks']);
        $this->assertFalse($entry['hasSuggestActions']);
    }

    // --- discoverFromTools with real CRM GetLead tool ---

    public function test_discover_from_crm_get_lead_registers_customer_presenter(): void
    {
        $tool = $this->app->make(\Vinkius\Vurb\Tests\Fixtures\Tools\Crm\GetLead::class);
        $tools = [
            'crm.get_lead' => [
                'tool' => $tool,
                'class' => \Vinkius\Vurb\Tests\Fixtures\Tools\Crm\GetLead::class,
                'router' => null,
                'middleware' => [],
                'tags' => [],
            ],
        ];

        $this->registry->discoverFromTools($tools);
        $all = $this->registry->all();

        $this->assertNotEmpty($all);
        $this->assertArrayHasKey('CustomerPresenter', $all);
        $this->assertSame(\Vinkius\Vurb\Tests\Fixtures\CustomerPresenter::class, $all['CustomerPresenter']);
    }

    public function test_compile_customer_presenter(): void
    {
        $this->registry->register(
            \Vinkius\Vurb\Tests\Fixtures\CustomerPresenter::class,
            'CustomerPresenter'
        );
        $compiled = $this->registry->compileAll();

        $entry = $compiled['CustomerPresenter'];
        $this->assertTrue($entry['isVurbPresenter']);
        $this->assertTrue($entry['hasSystemRules']);
        $this->assertTrue($entry['hasUiBlocks']);
        $this->assertTrue($entry['hasSuggestActions']);
    }
}
