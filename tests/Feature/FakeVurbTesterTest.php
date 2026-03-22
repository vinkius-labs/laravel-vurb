<?php

namespace Vinkius\Vurb\Tests\Feature;

use Vinkius\Vurb\Testing\FakeVurbTester;
use Vinkius\Vurb\Tests\Fixtures\Tools\FailingTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\Fixtures\Tools\SendNotification;
use Vinkius\Vurb\Tests\Fixtures\Tools\UpdateCustomer;
use Vinkius\Vurb\Tests\TestCase;

class FakeVurbTesterTest extends TestCase
{
    public function test_call_successful_query(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 42]);

        $this->assertFalse($result->isError);
        $result->assertSuccessful();
        $result->assertDataHasKey('id');
        $result->assertDataHasKey('name');
        $result->assertDataEquals('id', 42);
        $result->assertDataEquals('name', 'John Doe');
    }

    public function test_call_with_optional_params_defaulted(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 1]);

        $this->assertFalse($result->isError);
        $result->assertSuccessful();
        $result->assertDataEquals('include_orders', false);
    }

    public function test_call_with_optional_params_provided(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 1, 'include_orders' => true]);

        $this->assertFalse($result->isError);
        $result->assertSuccessful();
        $result->assertDataEquals('include_orders', true);
    }

    public function test_call_mutation_tool(): void
    {
        $result = FakeVurbTester::for(UpdateCustomer::class)
            ->call(['id' => 5, 'name' => 'Jane']);

        $this->assertFalse($result->isError);
        $result->assertSuccessful();
        $result->assertDataHasKey('id');
        $result->assertDataEquals('id', 5);
        $result->assertDataEquals('name', 'Jane');
    }

    public function test_call_action_tool_with_array_param(): void
    {
        $result = FakeVurbTester::for(SendNotification::class)
            ->call([
                'user_id' => 10,
                'message' => 'Hello',
                'channels' => ['email', 'sms'],
            ]);

        $this->assertFalse($result->isError);
        $result->assertSuccessful();
        $result->assertDataHasKey('user_id');
        $result->assertDataEquals('user_id', 10);
        $result->assertDataHasKey('channels');
    }

    public function test_call_missing_required_param_returns_validation_error(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call([]); // missing 'id'

        $this->assertTrue($result->isError);
        $result->assertIsError('VALIDATION_ERROR');
    }

    public function test_call_failing_tool_returns_internal_error(): void
    {
        $result = FakeVurbTester::for(FailingTool::class)
            ->call([]);

        $this->assertTrue($result->isError);
        $result->assertIsError('INTERNAL_ERROR');
    }

    public function test_result_has_tool_name(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 1]);

        $this->assertSame('customers.get_profile', $result->toolName);
    }

    public function test_result_has_latency(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 1]);

        $this->assertIsFloat($result->latencyMs);
        $this->assertGreaterThan(0, $result->latencyMs);
    }

    public function test_assert_data_missing_key(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 1]);

        $this->assertFalse($result->isError);
        $result->assertDataMissingKey('nonexistent_key');
    }

    public function test_get_input_schema(): void
    {
        $tester = FakeVurbTester::for(GetCustomerProfile::class);
        $schema = $tester->getInputSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
    }

    public function test_chained_assertions(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)
            ->call(['id' => 99]);

        $this->assertFalse($result->isError);
        // All assertion methods return $this for chaining
        $result
            ->assertSuccessful()
            ->assertDataHasKey('id')
            ->assertDataHasKey('name')
            ->assertDataEquals('id', 99)
            ->assertDataMissingKey('password');
    }
}
