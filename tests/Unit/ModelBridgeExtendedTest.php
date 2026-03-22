<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Vinkius\Vurb\Models\ModelBridge;
use Vinkius\Vurb\Models\ModelRegistry;
use Vinkius\Vurb\Tests\Fixtures\OrderStatus;
use Vinkius\Vurb\Tests\TestCase;

// --- Test fixture models (inline) ---

class TestModelWithDescriptions extends Model
{
    protected $table = 'test_models';
    protected $fillable = ['name', 'email', 'status'];

    protected $casts = [
        'status' => OrderStatus::class,
        'metadata' => 'json',
        'birthday' => 'date',
        'last_login' => 'datetime',
        'is_active' => 'boolean',
        'balance' => 'float',
        'age' => 'integer',
        'nickname' => 'string',
    ];

    protected array $vurbDescriptions = [
        'id' => 'Primary key',
        'name' => 'Full name',
        'email' => 'Email address',
        'status' => 'Current order status',
        'metadata' => 'Extra JSON data',
    ];
}

class TestModelPlain extends Model
{
    protected $table = 'plain_models';
    protected $fillable = ['title'];

    protected $casts = [
        'data' => 'collection',
        'settings' => 'array',
        'archived_at' => 'immutable_datetime',
        'immutable_date_field' => 'immutable_date',
        'rank' => 'double',
        'score' => 'decimal',
        'type' => 'real',
    ];
}

class ModelBridgeExtendedTest extends TestCase
{
    protected ModelBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge = new ModelBridge();
    }

    // --- castToSchemaType for various types ---

    public function test_cast_to_schema_type_json_returns_object(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertSame('object', $schema['fields']['metadata']['type']);
    }

    public function test_cast_to_schema_type_date_returns_date(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertSame('date', $schema['fields']['birthday']['type']);
    }

    public function test_cast_to_schema_type_datetime_resolves_correctly(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        // 'datetime' → class_exists('datetime') is true (PHP's \DateTime) → returns 'object'
        $this->assertSame('object', $schema['fields']['last_login']['type']);
    }

    public function test_cast_to_schema_type_boolean(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertSame('boolean', $schema['fields']['is_active']['type']);
    }

    public function test_cast_to_schema_type_float(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertSame('number', $schema['fields']['balance']['type']);
    }

    public function test_cast_to_schema_type_integer(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertSame('integer', $schema['fields']['age']['type']);
    }

    public function test_cast_to_schema_type_string(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertSame('string', $schema['fields']['nickname']['type']);
    }

    public function test_cast_to_schema_type_backed_enum_returns_enum(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertSame('enum', $schema['fields']['status']['type']);
        $this->assertContains('pending', $schema['fields']['status']['values']);
        $this->assertContains('shipped', $schema['fields']['status']['values']);
    }

    public function test_cast_to_schema_type_collection_returns_object(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);
        $this->assertSame('object', $schema['fields']['data']['type']);
    }

    public function test_cast_to_schema_type_array_returns_object(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);
        $this->assertSame('object', $schema['fields']['settings']['type']);
    }

    public function test_cast_to_schema_type_immutable_datetime_returns_timestamp(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);
        $this->assertSame('timestamp', $schema['fields']['archived_at']['type']);
    }

    public function test_cast_to_schema_type_immutable_date_returns_timestamp(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);
        $this->assertSame('timestamp', $schema['fields']['immutable_date_field']['type']);
    }

    public function test_cast_to_schema_type_double_returns_number(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);
        $this->assertSame('number', $schema['fields']['rank']['type']);
    }

    public function test_cast_to_schema_type_decimal_returns_number(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);
        $this->assertSame('number', $schema['fields']['score']['type']);
    }

    public function test_cast_to_schema_type_real_returns_number(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);
        $this->assertSame('number', $schema['fields']['type']['type']);
    }

    // --- getVurbDescriptions ---

    public function test_get_vurb_descriptions_when_model_has_property(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);

        $this->assertSame('Primary key', $schema['fields']['id']['label']);
        $this->assertSame('Current order status', $schema['fields']['status']['label']);
        $this->assertSame('Extra JSON data', $schema['fields']['metadata']['label']);
    }

    public function test_get_vurb_descriptions_falls_back_to_auto_generated(): void
    {
        $schema = $this->bridge->bridge(TestModelPlain::class);

        // No $vurbDescriptions, so label is auto-generated: ucfirst(str_replace('_', ' ', field))
        $this->assertSame('Data', $schema['fields']['data']['label']);
        $this->assertSame('Settings', $schema['fields']['settings']['label']);
    }

    // --- bridge() throws for non-model class ---

    public function test_bridge_throws_for_non_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->bridge->bridge(\stdClass::class);
    }

    // --- fillable profiles ---

    public function test_fillable_profiles_extracted(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertArrayHasKey('fillable', $schema);
        $this->assertArrayHasKey('create', $schema['fillable']);
        $this->assertArrayHasKey('update', $schema['fillable']);
        $this->assertContains('name', $schema['fillable']['create']);
    }

    // --- hidden fields ---

    public function test_hidden_fields_extracted(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertArrayHasKey('hidden', $schema);
    }

    // --- timestamps ---

    public function test_timestamps_included(): void
    {
        $schema = $this->bridge->bridge(TestModelWithDescriptions::class);
        $this->assertArrayHasKey('created_at', $schema['fields']);
        $this->assertArrayHasKey('updated_at', $schema['fields']);
        $this->assertSame('timestamp', $schema['fields']['created_at']['type']);
    }

    // --- ModelRegistry: register + compileAll ---

    public function test_model_registry_register_and_compile_all(): void
    {
        $registry = new ModelRegistry();
        $registry->register(TestModelWithDescriptions::class, 'TestModel');

        $compiled = $registry->compileAll();
        $this->assertArrayHasKey('TestModel', $compiled);
        $this->assertArrayHasKey('fields', $compiled['TestModel']);
        $this->assertArrayHasKey('fillable', $compiled['TestModel']);
    }

    // --- ModelRegistry: discoverFromModels + usesVurbSchema ---

    public function test_discover_from_models_ignores_classes_without_trait(): void
    {
        $registry = new ModelRegistry();
        $registry->discoverFromModels([TestModelWithDescriptions::class, TestModelPlain::class]);

        // Neither model uses HasVurbSchema, so nothing registered
        $this->assertEmpty($registry->all());
    }

    public function test_discover_from_models_ignores_nonexistent_classes(): void
    {
        $registry = new ModelRegistry();
        $registry->discoverFromModels(['App\\Models\\NonExistentModel']);

        $this->assertEmpty($registry->all());
    }

    public function test_model_registry_clear(): void
    {
        $registry = new ModelRegistry();
        $registry->register(TestModelWithDescriptions::class, 'Foo');
        $this->assertNotEmpty($registry->all());

        $registry->clear();
        $this->assertEmpty($registry->all());
    }
}
