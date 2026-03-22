<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Mockery;
use Symfony\Component\Process\Process as SymfonyProcess;
use Vinkius\Vurb\Events\DaemonStarted;
use Vinkius\Vurb\Events\DaemonStopped;
use Vinkius\Vurb\Exceptions\DaemonNotRunningException;
use Vinkius\Vurb\Services\DaemonManager;
use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Tests\Fixtures\Tools\FailingTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\Fixtures\Tools\ListOrders;
use Vinkius\Vurb\Tests\Fixtures\Tools\ProcessPayment;
use Vinkius\Vurb\Tests\Fixtures\Tools\SearchProducts;
use Vinkius\Vurb\Tests\Fixtures\Tools\SendNotification;
use Vinkius\Vurb\Tests\TestCase;

class RemainingGapsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── VurbTool::inferNameFromClass() ───

    public function test_infer_name_without_verb_prefix_returns_snake_case(): void
    {
        $tool = $this->app->make(FailingTool::class);
        // FailingTool has no recognized verb prefix → Str::snake('FailingTool') = 'failing_tool'
        $this->assertSame('failing_tool', $tool->name());
    }

    // ─── ReflectionEngine: ListOrders with OrderStatus enum param ───

    public function test_reflect_tool_list_orders_has_enum_values(): void
    {
        $engine = $this->app->make(ReflectionEngine::class);
        $tool = $this->app->make(ListOrders::class);
        $schema = $engine->reflectTool($tool);

        $this->assertSame('object', $schema['inputSchema']['type']);
        $statusProp = $schema['inputSchema']['properties']['status'];
        $this->assertSame('string', $statusProp['type']);
        $this->assertIsArray($statusProp['enum']);
        $this->assertContains('pending', $statusProp['enum']);
        $this->assertContains('shipped', $statusProp['enum']);
    }

    // ─── ReflectionEngine: SendNotification with array items:'string' ───

    public function test_reflect_tool_send_notification_has_array_items(): void
    {
        $engine = $this->app->make(ReflectionEngine::class);
        $tool = $this->app->make(SendNotification::class);
        $schema = $engine->reflectTool($tool);

        $channelsProp = $schema['inputSchema']['properties']['channels'];
        $this->assertSame('array', $channelsProp['type']);
        $this->assertSame(['type' => 'string'], $channelsProp['items']);
    }

    // ─── ReflectionEngine: SearchProducts with object shape items ───

    public function test_reflect_tool_search_products_has_object_shape_items(): void
    {
        $engine = $this->app->make(ReflectionEngine::class);
        $tool = $this->app->make(SearchProducts::class);
        $schema = $engine->reflectTool($tool);

        $filtersProp = $schema['inputSchema']['properties']['filters'];
        $this->assertSame('array', $filtersProp['type']);
        $this->assertSame('object', $filtersProp['items']['type']);
        $this->assertArrayHasKey('field', $filtersProp['items']['properties']);
    }

    // ─── ReflectionEngine: buildAnnotations for query/mutation/action ───

    public function test_annotations_query_is_readonly_idempotent(): void
    {
        $engine = $this->app->make(ReflectionEngine::class);
        $tool = $this->app->make(GetCustomerProfile::class);
        $schema = $engine->reflectTool($tool);

        $this->assertTrue($schema['annotations']['readOnly']);
        $this->assertFalse($schema['annotations']['destructive']);
        $this->assertTrue($schema['annotations']['idempotent']);
    }

    public function test_annotations_mutation_is_destructive(): void
    {
        $engine = $this->app->make(ReflectionEngine::class);
        $tool = $this->app->make(ProcessPayment::class);
        $schema = $engine->reflectTool($tool);

        $this->assertFalse($schema['annotations']['readOnly']);
        $this->assertTrue($schema['annotations']['destructive']);
        $this->assertFalse($schema['annotations']['idempotent']);
    }

    public function test_annotations_action_is_neutral(): void
    {
        $engine = $this->app->make(ReflectionEngine::class);
        $tool = $this->app->make(SendNotification::class);
        $schema = $engine->reflectTool($tool);

        $this->assertFalse($schema['annotations']['readOnly']);
        $this->assertFalse($schema['annotations']['destructive']);
        $this->assertTrue($schema['annotations']['idempotent']);
    }

    // ─── DaemonManager: writeStdin() when not running ───

    public function test_write_stdin_throws_when_no_process(): void
    {
        $daemon = new DaemonManager();
        $this->expectException(DaemonNotRunningException::class);
        $daemon->writeStdin('hello');
    }

    public function test_write_stdin_throws_when_process_not_running(): void
    {
        $daemon = new DaemonManager();
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(false);
        $this->setProcessOnDaemon($daemon, $mock);

        $this->expectException(DaemonNotRunningException::class);
        $daemon->writeStdin('hello');
    }

    public function test_write_stdin_writes_when_process_running(): void
    {
        $daemon = new DaemonManager();

        $inputStream = Mockery::mock();
        $inputStream->shouldReceive('write')->once()->with('test data');

        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('getInput')->andReturn($inputStream);

        $this->setProcessOnDaemon($daemon, $mock);

        $daemon->writeStdin('test data');
        // No exception = success
        $this->assertTrue(true);
    }

    // ─── DaemonManager: isRunning() false when no process ───

    public function test_is_running_false_when_no_process(): void
    {
        $daemon = new DaemonManager();
        $this->assertFalse($daemon->isRunning());
    }

    // ─── DaemonManager: getProcessInfo() null when no process ───

    public function test_get_process_info_null_when_no_process(): void
    {
        $daemon = new DaemonManager();
        $this->assertNull($daemon->getProcessInfo());
    }

    // ─── DaemonManager: getProcess() null when no process ───

    public function test_get_process_null_when_no_process(): void
    {
        $daemon = new DaemonManager();
        $this->assertNull($daemon->getProcess());
    }

    // ─── DaemonManager: stop() with mocked process and events ───

    public function test_stop_with_running_process_dispatches_event(): void
    {
        Event::fake([DaemonStopped::class]);

        $this->app['config']->set('vurb.observability.events', true);

        $daemon = new DaemonManager();
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('getPid')->andReturn(12345);
        $mock->shouldReceive('stop')->once()->with(10);

        $this->setProcessOnDaemon($daemon, $mock);

        $daemon->stop();

        $this->assertNull($daemon->getProcess());
        Event::assertDispatched(DaemonStopped::class, fn ($e) => $e->pid === 12345);
    }

    public function test_stop_with_running_process_events_disabled(): void
    {
        Event::fake([DaemonStopped::class]);

        $this->app['config']->set('vurb.observability.events', false);

        $daemon = new DaemonManager();
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('getPid')->andReturn(99999);
        $mock->shouldReceive('stop')->once()->with(10);

        $this->setProcessOnDaemon($daemon, $mock);

        $daemon->stop();

        $this->assertNull($daemon->getProcess());
        Event::assertNotDispatched(DaemonStopped::class);
    }

    // ─── DaemonManager: waitForReady() — no process → false ───

    public function test_wait_for_ready_returns_false_when_no_process(): void
    {
        $daemon = new DaemonManager();
        $this->assertFalse($daemon->waitForReady(100));
    }

    public function test_wait_for_ready_returns_false_when_process_exits(): void
    {
        $daemon = new DaemonManager();
        $mock = Mockery::mock(SymfonyProcess::class);
        $mock->shouldReceive('getIncrementalOutput')->andReturn('some output');
        $mock->shouldReceive('isRunning')->andReturn(false);

        $this->setProcessOnDaemon($daemon, $mock);

        $this->assertFalse($daemon->waitForReady(5000));
    }

    // ─── Helper ───

    protected function setProcessOnDaemon(DaemonManager $daemon, $process): void
    {
        $ref = new \ReflectionClass($daemon);
        $prop = $ref->getProperty('process');
        $prop->setAccessible(true);
        $prop->setValue($daemon, $process);
    }
}
