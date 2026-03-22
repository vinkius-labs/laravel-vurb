<?php

namespace Vinkius\Vurb\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

class ModelBridge
{
    /**
     * Convert an Eloquent Model class into a defineModel() manifest schema.
     */
    public function bridge(string $modelClass): array
    {
        if (! is_a($modelClass, Model::class, true)) {
            throw new \InvalidArgumentException("{$modelClass} is not an Eloquent Model.");
        }

        $model = new $modelClass();
        $ref = new ReflectionClass($model);

        $schema = [
            'fields' => $this->extractFields($model),
            'hidden' => $model->getHidden(),
            'fillable' => $this->extractFillableProfiles($model, $ref),
        ];

        return $schema;
    }

    /**
     * Extract field definitions from the model's $casts.
     */
    protected function extractFields(Model $model): array
    {
        $fields = [];
        $casts = $model->getCasts();
        $descriptions = $this->getVurbDescriptions($model);

        // Always include primary key
        $primaryKey = $model->getKeyName();
        $fields[$primaryKey] = [
            'type' => $model->getKeyType() === 'int' ? 'integer' : 'string',
            'label' => $descriptions[$primaryKey] ?? ucfirst(str_replace('_', ' ', $primaryKey)),
        ];

        foreach ($casts as $field => $castType) {
            $fields[$field] = [
                'type' => $this->castToSchemaType($castType),
                'label' => $descriptions[$field] ?? ucfirst(str_replace('_', ' ', $field)),
            ];

            // Enum: add values
            if (is_string($castType) && is_a($castType, BackedEnum::class, true)) {
                $cases = $castType::cases();
                $fields[$field]['type'] = 'enum';
                $fields[$field]['values'] = array_map(fn ($c) => $c->value, $cases);
            }
        }

        // Add timestamps if the model uses them
        if ($model->usesTimestamps()) {
            $createdAt = $model->getCreatedAtColumn();
            $updatedAt = $model->getUpdatedAtColumn();

            if ($createdAt && ! isset($fields[$createdAt])) {
                $fields[$createdAt] = [
                    'type' => 'timestamp',
                    'label' => $descriptions[$createdAt] ?? 'Created at',
                ];
            }

            if ($updatedAt && ! isset($fields[$updatedAt])) {
                $fields[$updatedAt] = [
                    'type' => 'timestamp',
                    'label' => $descriptions[$updatedAt] ?? 'Updated at',
                ];
            }
        }

        return $fields;
    }

    /**
     * Convert Eloquent cast type to schema type.
     */
    protected function castToSchemaType(string $castType): string
    {
        // Handle class-based casts (e.g., enum classes)
        if (class_exists($castType)) {
            if (is_a($castType, BackedEnum::class, true)) {
                return 'enum';
            }
            return 'object';
        }

        return match ($castType) {
            'int', 'integer' => 'integer',
            'float', 'double', 'decimal', 'real' => 'number',
            'bool', 'boolean' => 'boolean',
            'string' => 'string',
            'array', 'json', 'collection' => 'object',
            'date' => 'date',
            'datetime', 'immutable_date', 'immutable_datetime', 'timestamp' => 'timestamp',
            default => 'string',
        };
    }

    /**
     * Extract fillable profiles from the model.
     */
    protected function extractFillableProfiles(Model $model, ReflectionClass $ref): array
    {
        // Check for custom $vurbFillable property
        if ($ref->hasProperty('vurbFillable')) {
            $prop = $ref->getProperty('vurbFillable');
            $prop->setAccessible(true);
            return $prop->getValue($model);
        }

        // Default: use $fillable for both create and update
        $fillable = $model->getFillable();

        if (empty($fillable)) {
            return [];
        }

        return [
            'create' => $fillable,
            'update' => $fillable,
        ];
    }

    /**
     * Get the field descriptions from the model's $vurbDescriptions property.
     */
    protected function getVurbDescriptions(Model $model): array
    {
        $ref = new ReflectionClass($model);

        if (! $ref->hasProperty('vurbDescriptions')) {
            return [];
        }

        $prop = $ref->getProperty('vurbDescriptions');
        $prop->setAccessible(true);

        return $prop->getValue($model) ?? [];
    }
}
