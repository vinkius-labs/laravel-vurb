<?php

namespace Vinkius\Vurb\Tests\Unit;

use ReflectionClass;
use ReflectionMethod;
use Vinkius\Vurb\Attributes\Cached;
use Vinkius\Vurb\Attributes\Concurrency;
use Vinkius\Vurb\Attributes\Description;
use Vinkius\Vurb\Attributes\FsmBind;
use Vinkius\Vurb\Attributes\Instructions;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Attributes\Presenter;
use Vinkius\Vurb\Attributes\Stale;
use Vinkius\Vurb\Attributes\Tags;
use Vinkius\Vurb\Presenters\VurbPresenter;
use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Tests\Fixtures\OrderStatus;
use Vinkius\Vurb\Tests\Fixtures\Tools\EchoTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\FailingTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\Fixtures\Tools\ListOrders;
use Vinkius\Vurb\Tests\Fixtures\Tools\ProcessPayment;
use Vinkius\Vurb\Tests\Fixtures\Tools\SearchProducts;
use Vinkius\Vurb\Tests\Fixtures\Tools\SendNotification;
use Vinkius\Vurb\Tests\Fixtures\Tools\UpdateCustomer;
use Vinkius\Vurb\Tests\TestCase;
use Vinkius\Vurb\Tools\VurbQuery;

class ReflectionEngineExtendedTest extends TestCase
{
    protected ReflectionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(ReflectionEngine::class);
    }

    // --- phpTypeToJsonSchema ---

    public function test_php_type_to_json_schema_int(): void
    {
        $result = $this->engine->phpTypeToJsonSchema('int');
        $this->assertSame(['type' => 'integer'], $result);
    }

    public function test_php_type_to_json_schema_float(): void
    {
        $result = $this->engine->phpTypeToJsonSchema('float');
        $this->assertSame(['type' => 'number'], $result);
    }

    public function test_php_type_to_json_schema_string(): void
    {
        $result = $this->engine->phpTypeToJsonSchema('string');
        $this->assertSame(['type' => 'string'], $result);
    }

    public function test_php_type_to_json_schema_bool(): void
    {
        $result = $this->engine->phpTypeToJsonSchema('bool');
        $this->assertSame(['type' => 'boolean'], $result);
    }

    public function test_php_type_to_json_schema_array_without_items(): void
    {
        $result = $this->engine->phpTypeToJsonSchema('array');
        $this->assertSame(['type' => 'array'], $result);
    }

    public function test_php_type_to_json_schema_array_with_string_items(): void
    {
        $param = new Param(items: 'string');
        $result = $this->engine->phpTypeToJsonSchema('array', $param);
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $result);
    }

    public function test_php_type_to_json_schema_array_with_enum_items(): void
    {
        $param = new Param(items: OrderStatus::class);
        $result = $this->engine->phpTypeToJsonSchema('array', $param);
        $this->assertSame('array', $result['type']);
        $this->assertArrayHasKey('items', $result);
        $this->assertSame('string', $result['items']['type']);
        $this->assertContains('pending', $result['items']['enum']);
    }

    public function test_php_type_to_json_schema_array_with_object_shape(): void
    {
        $param = new Param(items: ['field' => 'string', 'value' => 'integer']);
        $result = $this->engine->phpTypeToJsonSchema('array', $param);
        $this->assertSame('array', $result['type']);
        $this->assertArrayHasKey('items', $result);
        $this->assertSame('object', $result['items']['type']);
        $this->assertArrayHasKey('field', $result['items']['properties']);
        $this->assertArrayHasKey('value', $result['items']['properties']);
    }

    // --- resolveClassType ---

    public function test_resolve_class_type_datetime(): void
    {
        $result = $this->engine->phpTypeToJsonSchema(\DateTime::class);
        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $result);
    }

    public function test_resolve_class_type_carbon(): void
    {
        $result = $this->engine->phpTypeToJsonSchema(\DateTimeInterface::class);
        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $result);
    }

    // --- resolveEnumSchema ---

    public function test_resolve_enum_schema_for_order_status(): void
    {
        $result = $this->engine->phpTypeToJsonSchema(OrderStatus::class);
        $this->assertSame('string', $result['type']);
        $this->assertIsArray($result['enum']);
        $this->assertContains('pending', $result['enum']);
        $this->assertContains('processing', $result['enum']);
        $this->assertContains('shipped', $result['enum']);
        $this->assertContains('delivered', $result['enum']);
        $this->assertContains('cancelled', $result['enum']);
    }

    // --- buildAnnotations ---

    public function test_build_annotations_for_query(): void
    {
        $tool = $this->app->make(GetCustomerProfile::class);
        $schema = $this->engine->reflectTool($tool);
        $this->assertTrue($schema['annotations']['readOnly']);
        $this->assertFalse($schema['annotations']['destructive']);
        $this->assertTrue($schema['annotations']['idempotent']);
    }

    public function test_build_annotations_for_mutation(): void
    {
        $tool = $this->app->make(UpdateCustomer::class);
        $schema = $this->engine->reflectTool($tool);
        $this->assertFalse($schema['annotations']['readOnly']);
        $this->assertTrue($schema['annotations']['destructive']);
        $this->assertFalse($schema['annotations']['idempotent']);
    }

    public function test_build_annotations_for_action(): void
    {
        $tool = $this->app->make(SendNotification::class);
        $schema = $this->engine->reflectTool($tool);
        $this->assertFalse($schema['annotations']['readOnly']);
        $this->assertFalse($schema['annotations']['destructive']);
        $this->assertTrue($schema['annotations']['idempotent']);
    }

    // --- extractInstructions ---

    public function test_extract_instructions_from_attribute(): void
    {
        $tool = $this->app->make(ProcessPayment::class);
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayHasKey('instructions', $schema);
        $this->assertStringContainsString('confirming payment details', $schema['instructions']);
    }

    public function test_extract_instructions_absent(): void
    {
        $tool = $this->app->make(EchoTool::class);
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayNotHasKey('instructions', $schema);
    }

    // --- extractPresenter ---

    public function test_extract_presenter_from_tool_with_attribute(): void
    {
        // Create a temp tool with #[Presenter] — by using ProcessPayment which doesn't have it
        // We'll test with a fixture that has it. Let's create one inline.
        $tool = new #[Presenter(resource: \Vinkius\Vurb\Presenters\VurbPresenter::class)] class extends VurbQuery {
            public function description(): string { return 'Test'; }
            public function name(): string { return 'test.presenter_tool'; }
            public function handle(): array { return []; }
        };

        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayHasKey('presenter', $schema);
        $this->assertSame('VurbPresenter', $schema['presenter']);
    }

    public function test_extract_presenter_absent_returns_no_key(): void
    {
        $tool = $this->app->make(EchoTool::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayNotHasKey('presenter', $schema);
    }

    // --- extractStateSync ---

    public function test_extract_state_sync_cached_with_ttl(): void
    {
        $tool = $this->app->make(GetCustomerProfile::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayHasKey('stateSync', $schema);
        $this->assertSame('stale-after', $schema['stateSync']['policy']);
        $this->assertSame(60, $schema['stateSync']['ttl']);
    }

    public function test_extract_state_sync_stale(): void
    {
        $tool = new #[Stale] class extends VurbQuery {
            public function description(): string { return 'Test'; }
            public function name(): string { return 'test.stale_tool'; }
            public function handle(): array { return []; }
        };

        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayHasKey('stateSync', $schema);
        $this->assertSame('stale', $schema['stateSync']['policy']);
    }

    public function test_extract_state_sync_absent(): void
    {
        $tool = $this->app->make(SendNotification::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayNotHasKey('stateSync', $schema);
    }

    // --- extractFsmBind ---

    public function test_extract_fsm_bind_from_process_payment(): void
    {
        $tool = $this->app->make(ProcessPayment::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayHasKey('fsmBind', $schema);
        $this->assertSame(['payment'], $schema['fsmBind']['states']);
        $this->assertSame('PAY', $schema['fsmBind']['event']);
    }

    public function test_extract_fsm_bind_absent(): void
    {
        $tool = $this->app->make(EchoTool::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayNotHasKey('fsmBind', $schema);
    }

    // --- extractConcurrency ---

    public function test_extract_concurrency_from_process_payment(): void
    {
        $tool = $this->app->make(ProcessPayment::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayHasKey('concurrency', $schema);
        $this->assertSame(3, $schema['concurrency']['max']);
    }

    public function test_extract_concurrency_absent(): void
    {
        $tool = $this->app->make(EchoTool::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertArrayNotHasKey('concurrency', $schema);
    }

    // --- buildDescription with #[Description] override ---

    public function test_build_description_from_attribute_overrides_method(): void
    {
        $tool = new #[Description('Override description')] class extends VurbQuery {
            public function description(): string { return 'Original description'; }
            public function name(): string { return 'test.described'; }
            public function handle(): array { return []; }
        };

        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertSame('Override description', $schema['description']);
    }

    public function test_build_description_uses_method_when_no_attribute(): void
    {
        $tool = $this->app->make(EchoTool::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertSame('Echoes back all input parameters for testing.', $schema['description']);
    }

    // --- extractTags ---

    public function test_extract_tags_from_attribute(): void
    {
        $tool = $this->app->make(GetCustomerProfile::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertContains('crm', $schema['tags']);
        $this->assertContains('public', $schema['tags']);
    }

    // --- getLlmParameterNames ---

    public function test_get_llm_parameter_names(): void
    {
        $ref = new ReflectionClass(GetCustomerProfile::class);
        $method = $ref->getMethod('handle');
        $names = $this->engine->getLlmParameterNames($method);
        $this->assertSame(['id', 'include_orders'], $names);
    }

    public function test_get_llm_parameter_names_excludes_di_params(): void
    {
        // ListOrders has OrderStatus (enum = LLM param) + int page
        $ref = new ReflectionClass(ListOrders::class);
        $method = $ref->getMethod('handle');
        $names = $this->engine->getLlmParameterNames($method);
        $this->assertContains('status', $names);
        $this->assertContains('page', $names);
    }

    // --- findHandleMethod ---

    public function test_find_handle_method_returns_method(): void
    {
        $ref = new ReflectionClass(EchoTool::class);
        $method = $this->engine->findHandleMethod($ref);
        $this->assertInstanceOf(ReflectionMethod::class, $method);
        $this->assertSame('handle', $method->getName());
    }

    public function test_find_handle_method_returns_null_for_class_without_handle(): void
    {
        $ref = new ReflectionClass(\stdClass::class);
        $method = $this->engine->findHandleMethod($ref);
        $this->assertNull($method);
    }

    // --- emptySchema ---

    public function test_empty_schema(): void
    {
        // Tool without handle method generates empty schema
        $tool = new class extends VurbQuery {
            public function description(): string { return 'No handle'; }
            public function name(): string { return 'test.no_handle'; }
        };

        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        $this->assertSame('object', $schema['inputSchema']['type']);
        $this->assertInstanceOf(\stdClass::class, $schema['inputSchema']['properties']);
    }

    // --- clearCache ---

    public function test_clear_cache_resets_reflection_cache(): void
    {
        $tool = $this->app->make(EchoTool::class);
        $schema1 = $this->engine->reflectTool($tool);

        $this->engine->clearCache();

        // Re-reflect should produce same result
        $schema2 = $this->engine->reflectTool($tool);
        $this->assertSame($schema1['key'], $schema2['key']);
    }

    // --- extractActionKey ---

    public function test_action_key_extracted_from_dotted_name(): void
    {
        $tool = $this->app->make(GetCustomerProfile::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        // customers.get_profile → key = 'get_profile'
        $this->assertSame('get_profile', $schema['key']);
    }

    public function test_action_key_from_name_without_dot(): void
    {
        $tool = $this->app->make(FailingTool::class);
        $this->engine->clearCache();
        $schema = $this->engine->reflectTool($tool);
        // failing_tool → key = 'failing_tool' (no dot, returns full name)
        $this->assertSame('failing_tool', $schema['key']);
    }

    // --- reflectParameter with optional/default ---

    public function test_reflect_parameter_with_default(): void
    {
        $ref = new ReflectionClass(GetCustomerProfile::class);
        $method = $ref->getMethod('handle');
        $params = $method->getParameters();

        // include_orders has default = false
        $includeOrders = $params[1];
        $schema = $this->engine->reflectParameter($includeOrders);
        $this->assertSame('boolean', $schema['type']);
        $this->assertFalse($schema['default']);
        $this->assertSame('Include orders?', $schema['description']);
    }

    public function test_reflect_parameter_with_example(): void
    {
        $ref = new ReflectionClass(GetCustomerProfile::class);
        $method = $ref->getMethod('handle');
        $params = $method->getParameters();

        $idParam = $params[0];
        $schema = $this->engine->reflectParameter($idParam);
        $this->assertSame('integer', $schema['type']);
        $this->assertSame(42, $schema['x-example']);
        $this->assertSame('Customer ID', $schema['description']);
    }

    // --- Caching ---

    public function test_caching_returns_same_result(): void
    {
        $tool = $this->app->make(GetCustomerProfile::class);

        $first = $this->engine->reflectTool($tool);
        $second = $this->engine->reflectTool($tool);

        $this->assertSame($first, $second);
    }
}
