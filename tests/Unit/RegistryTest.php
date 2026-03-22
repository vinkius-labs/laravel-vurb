<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Models\ModelBridge;
use Vinkius\Vurb\Models\ModelRegistry;
use Vinkius\Vurb\Presenters\PresenterRegistry;
use Vinkius\Vurb\Presenters\VurbPresenter;
use Vinkius\Vurb\Tests\TestCase;

// ─── Minimal test presenter ───
class StubPresenter extends VurbPresenter
{
    public function toArray($request): array
    {
        return ['id' => $this->id];
    }

    public function systemRules(): array
    {
        return ['Always bold the name.'];
    }

    public function uiBlocks(): array
    {
        return [['type' => 'card']];
    }

    public function suggestActions(): array
    {
        return [['tool' => 'edit-customer']];
    }
}

// ─── Minimal test model ───
class StubModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'stub_models';
    protected $fillable = ['name', 'email'];
    protected $hidden = ['secret'];
    protected $casts = [
        'name' => 'string',
        'is_active' => 'boolean',
        'amount' => 'float',
    ];

    public array $vurbDescriptions = [
        'id' => 'Primary key',
        'name' => 'Full name',
    ];
}

class StubModelWithVurbFillable extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'stub_models';
    protected $casts = [];

    public array $vurbFillable = [
        'create' => ['name', 'email'],
        'update' => ['name'],
    ];
}

class StubModelNoFillable extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'stub_models';
    protected $fillable = [];
    protected $casts = [];
    public $timestamps = false;
}

class RegistryTest extends TestCase
{
    // ═══ PresenterRegistry ═══

    public function test_presenter_registry_register_and_get(): void
    {
        $registry = new PresenterRegistry();
        $registry->register(StubPresenter::class, 'MyPresenter');

        $this->assertSame(StubPresenter::class, $registry->get('MyPresenter'));
        $this->assertNull($registry->get('Nonexistent'));
    }

    public function test_presenter_registry_register_infers_alias(): void
    {
        $registry = new PresenterRegistry();
        $registry->register(StubPresenter::class);

        $this->assertSame(StubPresenter::class, $registry->get('StubPresenter'));
    }

    public function test_presenter_registry_all_and_clear(): void
    {
        $registry = new PresenterRegistry();
        $registry->register(StubPresenter::class, 'A');
        $registry->register(StubPresenter::class, 'B');

        $this->assertCount(2, $registry->all());

        $registry->clear();
        $this->assertEmpty($registry->all());
    }

    public function test_presenter_registry_compile_all(): void
    {
        $registry = new PresenterRegistry();
        $registry->register(StubPresenter::class, 'MyStub');

        $compiled = $registry->compileAll();

        $this->assertArrayHasKey('MyStub', $compiled);
        $entry = $compiled['MyStub'];
        $this->assertSame('MyStub', $entry['name']);
        $this->assertSame(StubPresenter::class, $entry['class']);
        $this->assertTrue($entry['isVurbPresenter']);
        $this->assertSame(['type' => 'object'], $entry['schema']);
        // StubPresenter overrides all three methods
        $this->assertTrue($entry['hasSystemRules']);
        $this->assertTrue($entry['hasUiBlocks']);
        $this->assertTrue($entry['hasSuggestActions']);
    }

    public function test_presenter_discover_from_tools_with_no_attribute(): void
    {
        $registry = new PresenterRegistry();
        $tools = [
            ['tool' => new \Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile()],
        ];
        $registry->discoverFromTools($tools);

        $this->assertEmpty($registry->all());
    }

    // ═══ ModelBridge ═══

    public function test_model_bridge_rejects_non_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $bridge = new ModelBridge();
        $bridge->bridge(\stdClass::class);
    }

    public function test_model_bridge_extracts_fields(): void
    {
        $bridge = new ModelBridge();
        $schema = $bridge->bridge(StubModel::class);

        $this->assertArrayHasKey('fields', $schema);

        $fields = $schema['fields'];
        $this->assertArrayHasKey('id', $fields);
        $this->assertSame('integer', $fields['id']['type']);
        $this->assertSame('Primary key', $fields['id']['label']);

        $this->assertSame('string', $fields['name']['type']);
        $this->assertSame('Full name', $fields['name']['label']);

        $this->assertSame('boolean', $fields['is_active']['type']);
        $this->assertSame('number', $fields['amount']['type']);
    }

    public function test_model_bridge_includes_hidden(): void
    {
        $bridge = new ModelBridge();
        $schema = $bridge->bridge(StubModel::class);

        $this->assertContains('secret', $schema['hidden']);
    }

    public function test_model_bridge_includes_timestamps(): void
    {
        $bridge = new ModelBridge();
        $schema = $bridge->bridge(StubModel::class);

        $this->assertArrayHasKey('created_at', $schema['fields']);
        $this->assertSame('timestamp', $schema['fields']['created_at']['type']);
        $this->assertArrayHasKey('updated_at', $schema['fields']);
    }

    public function test_model_bridge_fillable_profiles_default(): void
    {
        $bridge = new ModelBridge();
        $schema = $bridge->bridge(StubModel::class);

        $this->assertArrayHasKey('create', $schema['fillable']);
        $this->assertArrayHasKey('update', $schema['fillable']);
        $this->assertContains('name', $schema['fillable']['create']);
    }

    public function test_model_bridge_custom_vurb_fillable(): void
    {
        $bridge = new ModelBridge();
        $schema = $bridge->bridge(StubModelWithVurbFillable::class);

        $this->assertSame(['name', 'email'], $schema['fillable']['create']);
        $this->assertSame(['name'], $schema['fillable']['update']);
    }

    public function test_model_bridge_no_fillable_returns_empty(): void
    {
        $bridge = new ModelBridge();
        $schema = $bridge->bridge(StubModelNoFillable::class);

        $this->assertEmpty($schema['fillable']);
    }

    public function test_model_bridge_no_timestamps(): void
    {
        $bridge = new ModelBridge();
        $schema = $bridge->bridge(StubModelNoFillable::class);

        $this->assertArrayNotHasKey('created_at', $schema['fields']);
    }

    // ═══ ModelRegistry ═══

    public function test_model_registry_register_and_all(): void
    {
        $registry = new ModelRegistry();
        $registry->register(StubModel::class, 'Stub');

        $all = $registry->all();
        $this->assertArrayHasKey('Stub', $all);
        $this->assertSame(StubModel::class, $all['Stub']);
    }

    public function test_model_registry_clear(): void
    {
        $registry = new ModelRegistry();
        $registry->register(StubModel::class);
        $registry->clear();

        $this->assertEmpty($registry->all());
    }

    public function test_model_registry_compile_all(): void
    {
        $registry = new ModelRegistry();
        $registry->register(StubModel::class, 'Stub');

        $compiled = $registry->compileAll();
        $this->assertArrayHasKey('Stub', $compiled);
        $this->assertArrayHasKey('fields', $compiled['Stub']);
    }
}
