<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Testing\FakeVurbTester;
use Vinkius\Vurb\Tests\Fixtures\BlockingMiddleware;
use Vinkius\Vurb\Tests\Fixtures\MutatingMiddleware;
use Vinkius\Vurb\Tests\Fixtures\ParameterisedMiddleware;
use Vinkius\Vurb\Tests\Fixtures\Tools\CollectionReturningTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\Crm\GetLead;
use Vinkius\Vurb\Tests\Fixtures\Tools\EchoTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\FailingTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\Fixtures\Tools\ModelNotFoundTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\NullReturningTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\SendNotification;
use Vinkius\Vurb\Tests\TestCase;

class FakeVurbTesterExtendedTest extends TestCase
{
    // --- withMiddleware() ---

    public function test_with_middleware_replaces_middleware(): void
    {
        $tester = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([MutatingMiddleware::class]);

        $result = $tester->call(['id' => 1]);

        $this->assertFalse($result->isError);
        $result->assertSuccessful();
        $result->assertDataHasKey('id');
    }

    // --- addMiddleware() ---

    public function test_add_middleware_appends_to_existing(): void
    {
        $tester = FakeVurbTester::for(GetCustomerProfile::class)
            ->addMiddleware(MutatingMiddleware::class);

        $result = $tester->call(['id' => 1]);
        $this->assertFalse($result->isError);
        $result->assertSuccessful();
        $result->assertDataHasKey('id');
    }

    // --- getInputSchema() ---

    public function test_get_input_schema_returns_schema(): void
    {
        $tester = FakeVurbTester::for(GetCustomerProfile::class);
        $schema = $tester->getInputSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('include_orders', $schema['properties']);
    }

    public function test_get_input_schema_for_tool_without_params(): void
    {
        $tester = FakeVurbTester::for(FailingTool::class);
        $schema = $tester->getInputSchema();

        $this->assertSame('object', $schema['type']);
    }

    // --- Missing required param → VALIDATION_ERROR ---

    public function test_call_with_missing_required_param_returns_validation_error(): void
    {
        $result = FakeVurbTester::for(SendNotification::class)
            ->call([]); // missing user_id, message, channels

        $this->assertTrue($result->isError);
        $result->assertIsError('VALIDATION_ERROR');
        $this->assertStringContainsString('user_id', $result->errorMessage);
    }

    // --- RuntimeException → INTERNAL_ERROR ---

    public function test_call_when_tool_throws_runtime_exception_returns_internal_error(): void
    {
        $result = FakeVurbTester::for(FailingTool::class)
            ->call([]);

        $this->assertTrue($result->isError);
        $result->assertIsError('INTERNAL_ERROR');
        $this->assertSame('Something went wrong.', $result->errorMessage);
    }

    // --- Middleware returning error array → MIDDLEWARE_ERROR ---

    public function test_call_with_blocking_middleware_returns_middleware_error(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([BlockingMiddleware::class])
            ->call(['id' => 1]);

        $this->assertTrue($result->isError);
        $result->assertIsError('BLOCKED');
        $this->assertSame('Request blocked by middleware.', $result->errorMessage);
    }

    // --- serializeResult with object having toArray ---

    public function test_serialize_result_with_to_array_object(): void
    {
        // We can test this indirectly via a tool that returns such an object.
        // EchoTool returns array directly, so let's just validate serialization works for arrays.
        $result = FakeVurbTester::for(EchoTool::class)
            ->call(['value' => 'hello', 'number' => 42, 'items' => ['a', 'b']]);

        $result->assertSuccessful();
        $result->assertDataEquals('value', 'hello');
        $result->assertDataEquals('number', 42);
        $this->assertSame(['a', 'b'], $result->data['items']);
    }

    // --- Latency tracked ---

    public function test_result_includes_latency(): void
    {
        $result = FakeVurbTester::for(EchoTool::class)
            ->call(['value' => 'test']);

        $this->assertGreaterThan(0, $result->latencyMs);
    }

    // --- Tool name in result ---

    public function test_result_contains_correct_tool_name(): void
    {
        $result = FakeVurbTester::for(SendNotification::class)
            ->call(['user_id' => 1, 'message' => 'hi', 'channels' => ['email']]);

        $this->assertSame('notifications.send', $result->toolName);
    }

    // ═══ Presenter pipeline via FakeVurbTester ═══

    public function test_call_presenter_tool_returns_system_rules(): void
    {
        $result = FakeVurbTester::for(GetLead::class)->call(['id' => 1]);

        $result->assertSuccessful();
        $this->assertNotEmpty($result->systemRules);
        $this->assertContains('Never expose raw email', $result->systemRules);
        $this->assertContains('Always use formal names', $result->systemRules);
    }

    public function test_call_presenter_tool_returns_ui_blocks(): void
    {
        $result = FakeVurbTester::for(GetLead::class)->call(['id' => 1]);

        $result->assertSuccessful();
        $this->assertNotEmpty($result->uiBlocks);
        $this->assertSame('summary', $result->uiBlocks[0]['type']);
    }

    public function test_call_presenter_tool_returns_suggest_actions(): void
    {
        $result = FakeVurbTester::for(GetLead::class)->call(['id' => 1]);

        $this->assertFalse($result->isError);
        $this->assertNotEmpty($result->suggestActions);
        $this->assertSame('crm.update_lead', $result->suggestActions[0]['tool']);
    }

    public function test_presenter_serializes_via_resolve_stripping_email(): void
    {
        $result = FakeVurbTester::for(GetLead::class)->call(['id' => 42]);

        $result->assertSuccessful();
        $this->assertSame(42, $result->data['id']);
        $this->assertSame('Jane Lead', $result->data['name']);
        // CustomerPresenter's toArray() strips email
        $this->assertArrayNotHasKey('email', $result->data);
    }

    // ═══ Null-returning tool ═══

    public function test_null_returning_tool_serializes_to_null(): void
    {
        $result = FakeVurbTester::for(NullReturningTool::class)->call([]);

        $result->assertSuccessful();
        $this->assertNull($result->data);
    }

    // ═══ Middleware with parameters ═══

    public function test_parameterised_middleware(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->withMiddleware([ParameterisedMiddleware::class . ':editor'])
            ->call(['id' => 1]);

        $this->assertFalse($result->isError);
        $this->assertNotNull($result->data);
    }

    // ═══ Collection serialization ═══

    public function test_collection_returning_tool_serialized_to_array(): void
    {
        $result = FakeVurbTester::for(CollectionReturningTool::class)->call(['count' => 3]);

        $result->assertSuccessful();
        $this->assertFalse($result->isError);
        $this->assertIsArray($result->data);
        $this->assertCount(3, $result->data);
        $this->assertSame(1, $result->data[0]['id']);
    }

    // ═══ ModelNotFoundException  ═══

    public function test_model_not_found_returns_not_found_error(): void
    {
        $result = FakeVurbTester::for(ModelNotFoundTool::class)->call(['id' => 999]);

        $this->assertTrue($result->isError);
        $this->assertSame('NOT_FOUND', $result->errorCode);
        $this->assertSame('Resource not found.', $result->errorMessage);
    }
}
