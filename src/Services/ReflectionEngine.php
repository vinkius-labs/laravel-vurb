<?php

namespace Vinkius\Vurb\Services;

use BackedEnum;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Vinkius\Vurb\Attributes\AgentLimit;
use Vinkius\Vurb\Attributes\Cached;
use Vinkius\Vurb\Attributes\Concurrency;
use Vinkius\Vurb\Attributes\Description;
use Vinkius\Vurb\Attributes\FsmBind;
use Vinkius\Vurb\Attributes\Hidden;
use Vinkius\Vurb\Attributes\Instructions;
use Vinkius\Vurb\Attributes\Invalidates;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Attributes\Presenter;
use Vinkius\Vurb\Attributes\Stale;
use Vinkius\Vurb\Attributes\Tags;
use Vinkius\Vurb\Tools\VurbTool;

class ReflectionEngine
{
    protected array $cache = [];

    /**
     * Reflect a VurbTool class into a complete action schema for the manifest.
     */
    public function reflectTool(VurbTool $tool): array
    {
        $class = get_class($tool);

        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $ref = new ReflectionClass($tool);
        $handleMethod = $this->findHandleMethod($ref);

        $schema = [
            'key' => $this->extractActionKey($tool->name()),
            'verb' => $tool->verb(),
            'description' => $this->buildDescription($tool, $ref),
            'inputSchema' => $handleMethod ? $this->buildInputSchema($handleMethod) : $this->emptySchema(),
            'tags' => $this->extractTags($tool, $ref),
            'annotations' => $this->buildAnnotations($tool),
        ];

        // Instructions
        $instructions = $this->extractInstructions($tool, $ref);
        if ($instructions !== null) {
            $schema['instructions'] = $instructions;
        }

        // Presenter
        $presenter = $this->extractPresenter($ref);
        if ($presenter !== null) {
            $schema['presenter'] = $presenter;
        }

        // State Sync
        $stateSync = $this->extractStateSync($ref);
        if ($stateSync !== null) {
            $schema['stateSync'] = $stateSync;
        }

        // FSM Binding
        $fsmBind = $this->extractFsmBind($ref);
        if ($fsmBind !== null) {
            $schema['fsmBind'] = $fsmBind;
        }

        // Concurrency
        $concurrency = $this->extractConcurrency($ref);
        if ($concurrency !== null) {
            $schema['concurrency'] = $concurrency;
        }

        $this->cache[$class] = $schema;

        return $schema;
    }

    /**
     * Build the JSON Schema for a handle() method's parameters.
     */
    public function buildInputSchema(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];

        foreach ($method->getParameters() as $param) {
            if ($this->isServiceContainerParam($param)) {
                continue;
            }

            $paramSchema = $this->reflectParameter($param);
            $properties[$param->getName()] = $paramSchema;

            if (! $param->isOptional() && ! $param->allowsNull()) {
                $required[] = $param->getName();
            }
        }

        if (empty($properties)) {
            return $this->emptySchema();
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Reflect a single parameter into a JSON Schema property.
     */
    public function reflectParameter(ReflectionParameter $param): array
    {
        $schema = [];
        $type = $param->getType();
        $paramAttr = $this->getParamAttribute($param);

        // Resolve type
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            $schema = $this->phpTypeToJsonSchema($typeName, $paramAttr);
        }

        // Description from #[Param]
        if ($paramAttr?->description !== null) {
            $schema['description'] = $paramAttr->description;
        }

        // Example from #[Param]
        if ($paramAttr?->example !== null) {
            // Store as x-example to avoid JSON Schema validation issues
            $schema['x-example'] = $paramAttr->example;
        }

        // Default value
        if ($param->isDefaultValueAvailable()) {
            $schema['default'] = $param->getDefaultValue();
        }

        return $schema;
    }

    /**
     * Convert a PHP type to JSON Schema.
     */
    public function phpTypeToJsonSchema(string $typeName, ?Param $paramAttr = null): array
    {
        return match ($typeName) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'string' => ['type' => 'string'],
            'bool' => ['type' => 'boolean'],
            'array' => $this->resolveArraySchema($paramAttr),
            default => $this->resolveClassType($typeName),
        };
    }

    /**
     * Resolve array type using #[Param(items:)] for inner type.
     */
    protected function resolveArraySchema(?Param $paramAttr): array
    {
        $schema = ['type' => 'array'];

        if ($paramAttr?->items === null) {
            return $schema;
        }

        $items = $paramAttr->items;

        if (is_string($items)) {
            // Scalar type or enum class
            if (in_array($items, ['string', 'integer', 'number', 'boolean'], true)) {
                $schema['items'] = ['type' => $items];
            } elseif (is_a($items, BackedEnum::class, true)) {
                $schema['items'] = $this->resolveEnumSchema($items);
            } else {
                // Treat as string fallback
                $schema['items'] = ['type' => $items];
            }
        } elseif (is_array($items)) {
            // Object shape: ['field' => 'type', ...]
            $schema['items'] = $this->resolveObjectShape($items);
        }

        return $schema;
    }

    /**
     * Resolve a class type (enum or service container).
     */
    protected function resolveClassType(string $typeName): array
    {
        // BackedEnum → enum values
        if (is_a($typeName, BackedEnum::class, true)) {
            return $this->resolveEnumSchema($typeName);
        }

        // Carbon/DateTime types
        if (is_a($typeName, \DateTimeInterface::class, true)) {
            return ['type' => 'string', 'format' => 'date-time'];
        }

        // Fallback: treat as string
        return ['type' => 'string'];
    }

    /**
     * Resolve a BackedEnum to JSON Schema with enum values.
     */
    protected function resolveEnumSchema(string $enumClass): array
    {
        $cases = $enumClass::cases();
        $values = array_map(fn (BackedEnum $case) => $case->value, $cases);

        $type = is_int($cases[0]->value) ? 'integer' : 'string';

        return ['type' => $type, 'enum' => $values];
    }

    /**
     * Resolve an object shape from an array definition.
     */
    protected function resolveObjectShape(array $shape): array
    {
        $properties = [];
        $required = [];

        foreach ($shape as $key => $type) {
            if (is_string($type)) {
                $properties[$key] = ['type' => $type];
            } elseif (is_array($type)) {
                $properties[$key] = $this->resolveObjectShape($type);
            }
            $required[] = $key;
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Determine if a parameter should be resolved via Service Container (not LLM).
     *
     * Rules:
     * 1. Scalar types (int, float, string, bool) → LLM parameter
     * 2. array → LLM parameter
     * 3. BackedEnum → LLM parameter
     * 4. DateTime → LLM parameter
     * 5. Class with #[Param] → LLM parameter (override)
     * 6. Class in container → Service Container injection (ignored)
     * 7. else → Service Container
     */
    public function isServiceContainerParam(ReflectionParameter $param): bool
    {
        $type = $param->getType();

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        $typeName = $type->getName();

        // Scalar types → LLM parameter
        if (in_array($typeName, ['int', 'float', 'string', 'bool', 'array', 'mixed'], true)) {
            return false;
        }

        // BackedEnum → LLM parameter
        if (is_a($typeName, BackedEnum::class, true)) {
            return false;
        }

        // DateTime → LLM parameter
        if (is_a($typeName, \DateTimeInterface::class, true)) {
            return false;
        }

        // #[Param] attribute overrides to LLM parameter
        if (! empty($param->getAttributes(Param::class))) {
            return false;
        }

        // Everything else → Service Container
        return true;
    }

    /**
     * Get the LLM parameter names for a handle() method (excludes DI params).
     */
    public function getLlmParameterNames(ReflectionMethod $method): array
    {
        $names = [];

        foreach ($method->getParameters() as $param) {
            if (! $this->isServiceContainerParam($param)) {
                $names[] = $param->getName();
            }
        }

        return $names;
    }

    /**
     * Find the handle() method on a tool class.
     */
    public function findHandleMethod(ReflectionClass $ref): ?ReflectionMethod
    {
        if ($ref->hasMethod('handle')) {
            return $ref->getMethod('handle');
        }

        return null;
    }

    /**
     * Extract the action key from a tool name (part after the dot).
     */
    protected function extractActionKey(string $name): string
    {
        $parts = explode('.', $name, 2);

        return count($parts) === 2 ? $parts[1] : $parts[0];
    }

    /**
     * Build full description including instructions.
     */
    protected function buildDescription(VurbTool $tool, ReflectionClass $ref): string
    {
        $desc = $tool->description();

        // Check #[Description] attribute as override
        $descAttrs = $ref->getAttributes(Description::class);
        if (! empty($descAttrs)) {
            $desc = $descAttrs[0]->newInstance()->value;
        }

        return $desc;
    }

    /**
     * Extract instructions from method or attribute.
     */
    protected function extractInstructions(VurbTool $tool, ReflectionClass $ref): ?string
    {
        // Method override first
        $instructions = $tool->instructions();
        if ($instructions !== null) {
            return $instructions;
        }

        // #[Instructions] attribute
        $attrs = $ref->getAttributes(Instructions::class);
        if (! empty($attrs)) {
            return $attrs[0]->newInstance()->value;
        }

        return null;
    }

    /**
     * Extract tags from method and attribute.
     */
    protected function extractTags(VurbTool $tool, ReflectionClass $ref): array
    {
        $tags = $tool->tags();

        $attrs = $ref->getAttributes(Tags::class);
        if (! empty($attrs)) {
            $tags = array_unique(array_merge($tags, $attrs[0]->newInstance()->values));
        }

        return array_values($tags);
    }

    /**
     * Extract #[Presenter] resource class name.
     */
    protected function extractPresenter(ReflectionClass $ref): ?string
    {
        $attrs = $ref->getAttributes(Presenter::class);

        if (empty($attrs)) {
            return null;
        }

        return class_basename($attrs[0]->newInstance()->resource);
    }

    /**
     * Extract state sync config from #[Cached], #[Stale], #[Invalidates].
     */
    protected function extractStateSync(ReflectionClass $ref): ?array
    {
        $config = [];

        // #[Cached]
        $cachedAttrs = $ref->getAttributes(Cached::class);
        if (! empty($cachedAttrs)) {
            $cached = $cachedAttrs[0]->newInstance();
            $config['policy'] = $cached->ttl !== null ? 'stale-after' : 'cached';
            if ($cached->ttl !== null) {
                $config['ttl'] = $cached->ttl;
            }
        }

        // #[Stale]
        if (! empty($ref->getAttributes(Stale::class))) {
            $config['policy'] = 'stale';
        }

        // #[Invalidates]
        $invAttrs = $ref->getAttributes(Invalidates::class);
        if (! empty($invAttrs)) {
            $config['invalidates'] = $invAttrs[0]->newInstance()->patterns;
        }

        return empty($config) ? null : $config;
    }

    /**
     * Extract FSM binding from #[FsmBind].
     */
    protected function extractFsmBind(ReflectionClass $ref): ?array
    {
        $attrs = $ref->getAttributes(FsmBind::class);

        if (empty($attrs)) {
            return null;
        }

        $bind = $attrs[0]->newInstance();

        return [
            'states' => $bind->states,
            'event' => $bind->event,
        ];
    }

    /**
     * Extract concurrency from #[Concurrency].
     */
    protected function extractConcurrency(ReflectionClass $ref): ?array
    {
        $attrs = $ref->getAttributes(Concurrency::class);

        if (empty($attrs)) {
            return null;
        }

        return ['max' => $attrs[0]->newInstance()->max];
    }

    /**
     * Build MCP annotations from verb.
     */
    protected function buildAnnotations(VurbTool $tool): array
    {
        $verb = $tool->verb();

        $annotations = [
            'verb' => $verb,
        ];

        // OpenAPI-style hints for MCP clients
        $annotations += match ($verb) {
            'query' => ['readOnly' => true, 'destructive' => false, 'idempotent' => true],
            'mutation' => ['readOnly' => false, 'destructive' => true, 'idempotent' => false],
            'action' => ['readOnly' => false, 'destructive' => false, 'idempotent' => true],
            default => [],
        };

        // Include presenter class name if present
        $ref = new \ReflectionClass($tool);
        $presenter = $this->extractPresenter($ref);
        if ($presenter !== null) {
            $annotations['presenter'] = $presenter;
        }

        // Include tags from the tool
        $tags = $tool->tags();
        if (! empty($tags)) {
            $annotations['tags'] = $tags;
        }

        return $annotations;
    }

    /**
     * Get #[Param] attribute from a parameter.
     */
    protected function getParamAttribute(ReflectionParameter $param): ?Param
    {
        $attrs = $param->getAttributes(Param::class);

        if (empty($attrs)) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Empty JSON Schema object.
     */
    protected function emptySchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    /**
     * Clear the reflection cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
