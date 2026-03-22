<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Vinkius\Vurb\Models\Concerns\HasVurbSchema;
use Vinkius\Vurb\Models\ModelBridge;
use Vinkius\Vurb\Tests\TestCase;

// --- Test fixture model ---

class VurbSchemaTestModel extends Model
{
    use HasVurbSchema;

    protected $table = 'vurb_schema_test';
    protected $fillable = ['name', 'email'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

class HasVurbSchemaTest extends TestCase
{
    public function test_to_vurb_schema_delegates_to_model_bridge(): void
    {
        $model = new VurbSchemaTestModel();
        $schema = $model->toVurbSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('fields', $schema);
        $this->assertArrayHasKey('hidden', $schema);
        $this->assertArrayHasKey('fillable', $schema);
    }

    public function test_to_vurb_schema_includes_primary_key(): void
    {
        $model = new VurbSchemaTestModel();
        $schema = $model->toVurbSchema();

        $this->assertArrayHasKey('id', $schema['fields']);
        $this->assertSame('integer', $schema['fields']['id']['type']);
    }

    public function test_to_vurb_schema_includes_cast_fields(): void
    {
        $model = new VurbSchemaTestModel();
        $schema = $model->toVurbSchema();

        $this->assertArrayHasKey('is_active', $schema['fields']);
        $this->assertSame('boolean', $schema['fields']['is_active']['type']);
    }

    public function test_to_vurb_schema_uses_app_model_bridge(): void
    {
        // Verify that calling toVurbSchema resolves ModelBridge from the container
        $bridge = $this->app->make(ModelBridge::class);
        $expected = $bridge->bridge(VurbSchemaTestModel::class);

        $model = new VurbSchemaTestModel();
        $result = $model->toVurbSchema();

        $this->assertSame($expected, $result);
    }
}
