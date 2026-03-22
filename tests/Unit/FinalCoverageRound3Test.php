<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\App;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Vinkius\Vurb\Attributes\Description;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Middleware\VurbMiddleware;
use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Testing\FakeVurbTester;
use Vinkius\Vurb\Testing\MvaTestResult;
use Vinkius\Vurb\Tests\TestCase;
use Vinkius\Vurb\Tools\VurbTool;

// ─── BackedEnum for ReflectionEngine tests ───────────────────
enum FinalCov3_Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}

enum FinalCov3_IntPriority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}

// ─── Tool with BackedEnum param (for ReflectionEngine line 96, 219, 278) ───
class FinalCov3_EnumTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with enum parameter';
    }

    public function handle(FinalCov3_Priority $priority): array
    {
        return ['priority' => $priority->value];
    }
}

// ─── Tool with array of enums (for line 193) ───
class FinalCov3_ArrayEnumTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with array of enums';
    }

    public function handle(
        #[Param(description: 'Priorities', items: FinalCov3_Priority::class)] array $priorities,
    ): array {
        return ['count' => count($priorities)];
    }
}

// ─── Tool with nested array object shape (for lines 246-247) ───
class FinalCov3_NestedObjectTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with nested array object shape';
    }

    public function handle(
        #[Param(description: 'Address info', items: ['street' => 'string', 'coords' => ['lat' => 'number', 'lng' => 'number']])] array $addresses,
    ): array {
        return ['count' => count($addresses)];
    }
}

// ─── Tool with #[Description] attribute override (for lines 299-304) ───
#[Description('Overridden description from attribute')]
class FinalCov3_DescriptionTool extends VurbTool
{
    public function description(): string
    {
        return 'Original description';
    }

    public function handle(): array
    {
        return [];
    }
}

// ─── Tool with single-word name (for lines 294-295—extractActionKey no dot) ───
class FinalCov3_SimpleTool extends VurbTool
{
    public function name(): string
    {
        return 'simpletool';
    }

    public function description(): string
    {
        return 'A simple single-word tool';
    }

    public function handle(): array
    {
        return [];
    }
}

// ─── Tool with #[Param] on a class type (for line 369) and defaults/nullable ───
class FinalCov3_ParamAttrTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with Param attribute';
    }

    public function handle(
        #[Param(description: 'Some value')] string $value = 'default_val',
        ?string $nullable_param = null,
    ): array {
        return ['value' => $value, 'nullable' => $nullable_param];
    }
}

// ─── Middleware that accepts parameters (for FakeVurbTester lines 215-226) ───
class FinalCov3_ParamMiddleware implements VurbMiddleware
{
    public function handle(array $context, \Closure $next, string ...$params): mixed
    {
        $context['input']['_mw_params'] = $params;
        return $next($context);
    }
}

// ─── Tool for FakeVurbTester default/null param tests ───
class FinalCov3_DefaultParamTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with default and nullable params';
    }

    public function handle(
        string $required_param,
        string $default_param = 'fallback',
        ?string $nullable_param = null,
    ): array {
        return [
            'required' => $required_param,
            'default' => $default_param,
            'nullable' => $nullable_param,
        ];
    }
}

// ─── Object with toArray() for serializeResult line 274 ───
class FinalCov3_ToArrayObject
{
    public function toArray(): array
    {
        return ['serialized' => true];
    }
}

class FinalCov3_ToArrayTool extends VurbTool
{
    public function description(): string
    {
        return 'Returns object with toArray()';
    }

    public function handle(): object
    {
        return new FinalCov3_ToArrayObject();
    }
}

// ─── Tool with int enum param (for resolveEnumSchema integer path, line 219) ───
class FinalCov3_IntEnumTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with integer backed enum';
    }

    public function handle(FinalCov3_IntPriority $level): array
    {
        return ['level' => $level->value];
    }
}

// ─── Tool with container-injected param (for ReflectionEngine line 96, isServiceContainerParam line 304) ───
class FinalCov3_ContainerParamTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with DI and LLM params';
    }

    public function handle(\Psr\Log\LoggerInterface $logger, string $name): array
    {
        return ['name' => $name, 'has_logger' => $logger !== null];
    }
}

// ─── Tool with DateTime param (for isServiceContainerParam lines 294-295) ───
class FinalCov3_DateTimeTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with DateTime param';
    }

    public function handle(#[Param(description: 'When it happened')] \DateTimeImmutable $when): array
    {
        return ['when' => $when->format('Y-m-d')];
    }
}

// ─── Tool with class param + #[Param] (for isServiceContainerParam lines 299-300) ───
class FinalCov3_ClassParamAttrTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with class param having Param attr';
    }

    public function handle(#[Param(description: 'Payload object')] \stdClass $data, string $label): array
    {
        return ['label' => $label];
    }
}

// ─── Tool with nullable-only param (for FakeVurbTester lines 225-226) ───
class FinalCov3_NullableOnlyParamTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with nullable-only param';
    }

    public function handle(string $name, ?string $tag): array
    {
        return ['name' => $name, 'tag' => $tag];
    }
}

// ─── Tool returning Eloquent Model (for FakeVurbTester line 274) ───
class FinalCov3_ModelReturnTool extends VurbTool
{
    public static $modelInstance;

    public function description(): string
    {
        return 'Returns Eloquent model';
    }

    public function handle(): \Illuminate\Database\Eloquent\Model
    {
        return static::$modelInstance;
    }
}

// ─── Tool with untyped param (for ReflectionEngine isServiceContainerParam line 278) ───
class FinalCov3_UntypedParamTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with untyped param';
    }

    public function handle($value): array
    {
        return ['value' => $value];
    }
}

// ─── Tool with array of plain string items (for ReflectionEngine line 193) ───
class FinalCov3_StringItemsTool extends VurbTool
{
    public function description(): string
    {
        return 'Tool with string items array';
    }

    public function handle(
        #[Param(description: 'List of tags', items: 'string')] array $tags,
    ): array {
        return ['count' => count($tags)];
    }
}

/**
 * Tests covering:
 * - ReflectionEngine: enum schema (lines 96, 193, 219), array BackedEnum items,
 *   objectShape nested (246-247), isServiceContainerParam BackedEnum (278),
 *   extractActionKey no-dot (294-295), buildDescription with #[Description] (299-304),
 *   getParamAttribute (369)
 * - FakeVurbTester: middleware with params (215-226), default value (183),
 *   nullable param (188), serializeResult toArray (274)
 * - MvaTestResult: assertDataMissingKey failure path (57-59)
 */
class FinalCoverageRound3Test extends TestCase
{
    // ─── ReflectionEngine BackedEnum as parameter type ─────────

    #[Test]
    public function reflection_engine_reflects_string_backed_enum_parameter()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_EnumTool());

        $this->assertSame('string', $schema['inputSchema']['properties']['priority']['type']);
        $this->assertSame(['low', 'medium', 'high'], $schema['inputSchema']['properties']['priority']['enum']);
    }

    #[Test]
    public function reflection_engine_reflects_int_backed_enum_parameter()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_IntEnumTool());

        $this->assertSame('integer', $schema['inputSchema']['properties']['level']['type']);
        $this->assertSame([1, 2, 3], $schema['inputSchema']['properties']['level']['enum']);
    }

    #[Test]
    public function reflection_engine_resolves_array_of_backed_enum_items()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_ArrayEnumTool());

        $props = $schema['inputSchema']['properties']['priorities'];
        $this->assertSame('array', $props['type']);
        $this->assertArrayHasKey('items', $props);
        $this->assertSame('string', $props['items']['type']);
        $this->assertSame(['low', 'medium', 'high'], $props['items']['enum']);
    }

    #[Test]
    public function reflection_engine_resolves_nested_object_shape()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_NestedObjectTool());

        $props = $schema['inputSchema']['properties']['addresses'];
        $this->assertSame('array', $props['type']);
        $this->assertArrayHasKey('items', $props);
        $this->assertSame('object', $props['items']['type']);
        // Check nested coords
        $this->assertSame('object', $props['items']['properties']['coords']['type']);
        $this->assertSame('number', $props['items']['properties']['coords']['properties']['lat']['type']);
    }

    #[Test]
    public function reflection_engine_description_attribute_overrides()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_DescriptionTool());

        $this->assertSame('Overridden description from attribute', $schema['description']);
    }

    #[Test]
    public function reflection_engine_extract_action_key_no_dot()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_SimpleTool());

        // Name is 'simpletool' (no dot), so extractActionKey returns the full name
        $this->assertSame('simpletool', $schema['key']);
    }

    #[Test]
    public function reflection_engine_backed_enum_is_not_service_container_param()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $ref = new \ReflectionClass(FinalCov3_EnumTool::class);
        $handleMethod = $ref->getMethod('handle');
        $param = $handleMethod->getParameters()[0];

        $this->assertFalse($engine->isServiceContainerParam($param));
    }

    #[Test]
    public function reflection_engine_get_param_attribute()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_ParamAttrTool());

        $this->assertArrayHasKey('value', $schema['inputSchema']['properties']);
        $this->assertSame('Some value', $schema['inputSchema']['properties']['value']['description']);
        $this->assertSame('default_val', $schema['inputSchema']['properties']['value']['default']);
    }

    // ─── FakeVurbTester: middleware with parameters ─────────────

    #[Test]
    public function fake_vurb_tester_middleware_with_parameters()
    {
        $this->app->bind(FinalCov3_ParamMiddleware::class, fn () => new FinalCov3_ParamMiddleware());

        $tester = FakeVurbTester::for(FinalCov3_DefaultParamTool::class);
        $tester->withMiddleware([FinalCov3_ParamMiddleware::class . ':admin,read']);

        $result = $tester->call(['required_param' => 'test']);

        $result->assertSuccessful();
        $this->assertSame('test', $result->data['required']);
    }

    // ─── FakeVurbTester: default value and nullable param ──────

    #[Test]
    public function fake_vurb_tester_uses_default_and_nullable_params()
    {
        $tester = FakeVurbTester::for(FinalCov3_DefaultParamTool::class);

        // Only provide required param, let others use defaults
        $result = $tester->call(['required_param' => 'hello']);

        $result->assertSuccessful();
        $this->assertSame('hello', $result->data['required']);
        $this->assertSame('fallback', $result->data['default']);
        $this->assertNull($result->data['nullable']);
    }

    // ─── FakeVurbTester: serializeResult with toArray ──────────

    #[Test]
    public function fake_vurb_tester_serializes_object_with_to_array()
    {
        $tester = FakeVurbTester::for(FinalCov3_ToArrayTool::class);
        $result = $tester->call();

        $result->assertSuccessful();
        $this->assertTrue($result->data['serialized']);
    }

    // ─── MvaTestResult: assertDataMissingKey failure ───────────

    #[Test]
    public function mva_test_result_assert_data_missing_key_throws_when_key_present()
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['secret' => 'value', 'name' => 'John'],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage("Expected data to NOT have key 'secret'");

        $result->assertDataMissingKey('secret');
    }

    #[Test]
    public function mva_test_result_assert_data_missing_key_passes_when_key_absent()
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['name' => 'John'],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        // Should not throw
        $returned = $result->assertDataMissingKey('secret');
        $this->assertSame($result, $returned);
    }

    #[Test]
    public function mva_test_result_assert_data_missing_key_passes_when_data_not_array()
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: 'string data',
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        // Non-array data should pass
        $returned = $result->assertDataMissingKey('anything');
        $this->assertSame($result, $returned);
    }

    // ─── ReflectionEngine: resolveClassType for non-enum class ──

    #[Test]
    public function reflection_engine_php_type_to_json_schema_datetime()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $result = $engine->phpTypeToJsonSchema(\DateTimeImmutable::class);

        $this->assertSame('string', $result['type']);
        $this->assertSame('date-time', $result['format']);
    }

    #[Test]
    public function reflection_engine_php_type_to_json_schema_unknown_class()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $result = $engine->phpTypeToJsonSchema('SomeUnknownClass');

        $this->assertSame('string', $result['type']);
    }

    // ─── ReflectionEngine: container-injected param skipped in buildInputSchema ──

    #[Test]
    public function reflection_engine_skips_container_param_in_input_schema()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_ContainerParamTool());

        // LoggerInterface should NOT appear in inputSchema (it's a container param)
        $this->assertArrayNotHasKey('logger', $schema['inputSchema']['properties']);
        // 'name' should appear (it's an LLM param)
        $this->assertArrayHasKey('name', $schema['inputSchema']['properties']);
    }

    #[Test]
    public function reflection_engine_service_container_param_returns_true_for_interface()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $ref = new \ReflectionClass(FinalCov3_ContainerParamTool::class);
        $params = $ref->getMethod('handle')->getParameters();
        $loggerParam = $params[0]; // \Psr\Log\LoggerInterface $logger

        // LoggerInterface is not scalar, not BackedEnum, not DateTime, no #[Param] → true
        $this->assertTrue($engine->isServiceContainerParam($loggerParam));
    }

    #[Test]
    public function reflection_engine_datetime_is_not_service_container_param()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $ref = new \ReflectionClass(FinalCov3_DateTimeTool::class);
        $params = $ref->getMethod('handle')->getParameters();
        $whenParam = $params[0]; // \DateTimeImmutable $when (with #[Param])

        $this->assertFalse($engine->isServiceContainerParam($whenParam));
    }

    #[Test]
    public function reflection_engine_class_with_param_attr_is_not_service_container_param()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $ref = new \ReflectionClass(FinalCov3_ClassParamAttrTool::class);
        $params = $ref->getMethod('handle')->getParameters();
        $dataParam = $params[0]; // \stdClass $data with #[Param]

        $this->assertFalse($engine->isServiceContainerParam($dataParam));
    }

    // ─── FakeVurbTester: container param injection ─────────────

    #[Test]
    public function fake_vurb_tester_resolves_container_param()
    {
        $tester = FakeVurbTester::for(FinalCov3_ContainerParamTool::class);

        $result = $tester->call(['name' => 'world']);

        $result->assertSuccessful();
        $this->assertSame('world', $result->data['name']);
        $this->assertTrue($result->data['has_logger']);
    }

    // ─── FakeVurbTester: nullable-only param (no default) ──────

    #[Test]
    public function fake_vurb_tester_passes_null_for_nullable_only_param()
    {
        $tester = FakeVurbTester::for(FinalCov3_NullableOnlyParamTool::class);

        // Only provide 'name', skip 'tag' (nullable with no default)
        $result = $tester->call(['name' => 'hello']);

        $result->assertSuccessful();
        $this->assertSame('hello', $result->data['name']);
        $this->assertNull($result->data['tag']);
    }

    // ─── FakeVurbTester: Model serialization ───────────────────

    #[Test]
    public function fake_vurb_tester_serializes_eloquent_model()
    {
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            protected $guarded = [];

            public function toArray(): array
            {
                return ['id' => 42, 'name' => 'TestModel'];
            }
        };

        FinalCov3_ModelReturnTool::$modelInstance = $model;

        $tester = FakeVurbTester::for(FinalCov3_ModelReturnTool::class);
        $result = $tester->call();

        $result->assertSuccessful();
        $this->assertSame(42, $result->data['id']);
    }

    // ─── MvaTestResult: assertDataHasKey failure ───────────────

    #[Test]
    public function mva_test_result_assert_data_has_key_throws_when_key_missing()
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['name' => 'John'],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage("Expected data to have key 'missing_key'");

        $result->assertDataHasKey('missing_key');
    }

    // ─── ReflectionEngine: untyped param → isServiceContainerParam returns false ──

    #[Test]
    public function reflection_engine_untyped_param_is_not_service_container()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $ref = new \ReflectionClass(FinalCov3_UntypedParamTool::class);
        $param = $ref->getMethod('handle')->getParameters()[0];

        $this->assertFalse($engine->isServiceContainerParam($param));
    }

    #[Test]
    public function reflection_engine_reflects_untyped_param()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_UntypedParamTool());

        $this->assertArrayHasKey('value', $schema['inputSchema']['properties']);
    }

    // ─── ReflectionEngine: array with plain string items type ──

    #[Test]
    public function reflection_engine_resolves_string_items_for_array_param()
    {
        $engine = $this->app->make(ReflectionEngine::class);

        $schema = $engine->reflectTool(new FinalCov3_StringItemsTool());

        $props = $schema['inputSchema']['properties']['tags'];
        $this->assertSame('array', $props['type']);
        $this->assertArrayHasKey('items', $props);
        $this->assertSame('string', $props['items']['type']);
    }
}
