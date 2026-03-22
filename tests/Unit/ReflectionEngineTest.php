<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\Fixtures\Tools\ListOrders;
use Vinkius\Vurb\Tests\Fixtures\Tools\ProcessPayment;
use Vinkius\Vurb\Tests\Fixtures\Tools\SearchProducts;
use Vinkius\Vurb\Tests\Fixtures\Tools\SendNotification;
use Vinkius\Vurb\Tests\Fixtures\Tools\UpdateCustomer;
use Vinkius\Vurb\Tests\TestCase;

class ReflectionEngineTest extends TestCase
{
    protected ReflectionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(ReflectionEngine::class);
    }

    // --- reflectTool ---

    public function test_reflect_query_tool(): void
    {
        $tool = new GetCustomerProfile();
        $schema = $this->engine->reflectTool($tool);

        $this->assertSame('get_profile', $schema['key']);
        $this->assertSame('query', $schema['verb']);
        $this->assertSame('Retrieve a customer profile by ID.', $schema['description']);
        $this->assertContains('crm', $schema['tags']);
        $this->assertContains('public', $schema['tags']);

        // Annotations for query
        $this->assertTrue($schema['annotations']['readOnly']);
        $this->assertFalse($schema['annotations']['destructive']);

        // State sync from #[Cached(ttl: 60)]
        $this->assertSame('stale-after', $schema['stateSync']['policy']);
        $this->assertSame(60, $schema['stateSync']['ttl']);
    }

    public function test_reflect_mutation_tool(): void
    {
        $tool = new UpdateCustomer();
        $schema = $this->engine->reflectTool($tool);

        $this->assertSame('mutation', $schema['verb']);
        $this->assertFalse($schema['annotations']['readOnly']);
        $this->assertTrue($schema['annotations']['destructive']);

        // Invalidates
        $this->assertSame(['customers.*', 'reports.*'], $schema['stateSync']['invalidates']);
    }

    public function test_reflect_action_tool(): void
    {
        $tool = new SendNotification();
        $schema = $this->engine->reflectTool($tool);

        $this->assertSame('action', $schema['verb']);
        $this->assertFalse($schema['annotations']['readOnly']);
        $this->assertFalse($schema['annotations']['destructive']);
        $this->assertTrue($schema['annotations']['idempotent']);
    }

    public function test_reflect_tool_with_instructions_and_fsm(): void
    {
        $tool = new ProcessPayment();
        $schema = $this->engine->reflectTool($tool);

        $this->assertSame('Only call after confirming payment details with the user.', $schema['instructions']);
        $this->assertSame(['max' => 3], $schema['concurrency']);
        $this->assertSame(['states' => ['payment'], 'event' => 'PAY'], $schema['fsmBind']);
    }

    public function test_reflect_tool_caches_result(): void
    {
        $tool = new GetCustomerProfile();
        $schema1 = $this->engine->reflectTool($tool);
        $schema2 = $this->engine->reflectTool($tool);

        $this->assertSame($schema1, $schema2);
    }

    // --- buildInputSchema ---

    public function test_input_schema_required_and_optional(): void
    {
        $tool = new GetCustomerProfile();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');

        $schema = $this->engine->buildInputSchema($method);

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('include_orders', $schema['properties']);

        // 'id' is required, 'include_orders' is optional (has default)
        $this->assertContains('id', $schema['required']);
        $this->assertNotContains('include_orders', $schema['required'] ?? []);
    }

    public function test_input_schema_types(): void
    {
        $tool = new GetCustomerProfile();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');

        $schema = $this->engine->buildInputSchema($method);

        $this->assertSame('integer', $schema['properties']['id']['type']);
        $this->assertSame('boolean', $schema['properties']['include_orders']['type']);
        $this->assertFalse($schema['properties']['include_orders']['default']);
    }

    public function test_input_schema_nullable_parameter(): void
    {
        $tool = new UpdateCustomer();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');

        $schema = $this->engine->buildInputSchema($method);

        // 'email' is nullable optional — should NOT be in required
        $this->assertContains('id', $schema['required']);
        $this->assertContains('name', $schema['required']);
        $this->assertNotContains('email', $schema['required'] ?? []);
    }

    public function test_input_schema_string_array_items(): void
    {
        $tool = new SendNotification();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');

        $schema = $this->engine->buildInputSchema($method);

        $channels = $schema['properties']['channels'];
        $this->assertSame('array', $channels['type']);
        $this->assertSame(['type' => 'string'], $channels['items']);
    }

    public function test_input_schema_object_shape_items(): void
    {
        $tool = new SearchProducts();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');

        $schema = $this->engine->buildInputSchema($method);

        $filters = $schema['properties']['filters'];
        $this->assertSame('array', $filters['type']);
        $this->assertSame('object', $filters['items']['type']);
        $this->assertArrayHasKey('field', $filters['items']['properties']);
        $this->assertArrayHasKey('operator', $filters['items']['properties']);
        $this->assertArrayHasKey('value', $filters['items']['properties']);
    }

    public function test_input_schema_backed_enum(): void
    {
        $tool = new ListOrders();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');

        $schema = $this->engine->buildInputSchema($method);

        $statusProp = $schema['properties']['status'];
        $this->assertSame('string', $statusProp['type']);
        $this->assertContains('pending', $statusProp['enum']);
        $this->assertContains('shipped', $statusProp['enum']);
        $this->assertContains('cancelled', $statusProp['enum']);
        $this->assertCount(5, $statusProp['enum']);
    }

    public function test_input_schema_param_description_and_example(): void
    {
        $tool = new GetCustomerProfile();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');

        $schema = $this->engine->buildInputSchema($method);

        $idProp = $schema['properties']['id'];
        $this->assertSame('Customer ID', $idProp['description']);
        $this->assertSame(42, $idProp['x-example']);
    }

    // --- phpTypeToJsonSchema ---

    public function test_php_type_to_json_schema_int(): void
    {
        $this->assertSame(['type' => 'integer'], $this->engine->phpTypeToJsonSchema('int'));
    }

    public function test_php_type_to_json_schema_float(): void
    {
        $this->assertSame(['type' => 'number'], $this->engine->phpTypeToJsonSchema('float'));
    }

    public function test_php_type_to_json_schema_string(): void
    {
        $this->assertSame(['type' => 'string'], $this->engine->phpTypeToJsonSchema('string'));
    }

    public function test_php_type_to_json_schema_bool(): void
    {
        $this->assertSame(['type' => 'boolean'], $this->engine->phpTypeToJsonSchema('bool'));
    }

    public function test_php_type_to_json_schema_array_without_items(): void
    {
        $result = $this->engine->phpTypeToJsonSchema('array');
        $this->assertSame(['type' => 'array'], $result);
    }

    // --- isServiceContainerParam ---

    public function test_scalar_params_are_not_container_params(): void
    {
        $tool = new GetCustomerProfile();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');
        $params = $method->getParameters();

        // int $id → NOT a service container param
        $this->assertFalse($this->engine->isServiceContainerParam($params[0]));
        // bool $include_orders → NOT a service container param
        $this->assertFalse($this->engine->isServiceContainerParam($params[1]));
    }

    public function test_enum_param_is_not_container_param(): void
    {
        $tool = new ListOrders();
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');
        $params = $method->getParameters();

        // OrderStatus $status → NOT a service container param (BackedEnum)
        $this->assertFalse($this->engine->isServiceContainerParam($params[0]));
    }

    // --- Name Inference ---

    public function test_tool_name_inference_get_customer_profile(): void
    {
        $tool = new GetCustomerProfile();
        $this->assertSame('customers.get_profile', $tool->name());
    }

    public function test_tool_name_inference_update_customer(): void
    {
        $tool = new UpdateCustomer();
        $this->assertSame('customers.update', $tool->name());
    }

    public function test_tool_name_inference_send_notification(): void
    {
        $tool = new SendNotification();
        $this->assertSame('notifications.send', $tool->name());
    }

    public function test_tool_name_inference_search_products(): void
    {
        $tool = new SearchProducts();
        $this->assertSame('products.search', $tool->name());
    }

    public function test_tool_name_inference_process_payment(): void
    {
        $tool = new ProcessPayment();
        $this->assertSame('payments.process', $tool->name());
    }

    public function test_tool_name_inference_list_orders(): void
    {
        $tool = new ListOrders();
        $this->assertSame('orders.list', $tool->name());
    }
}
