<?php

namespace Vinkius\Vurb\Testing;

use Illuminate\Support\Facades\App;
use ReflectionMethod;
use ReflectionNamedType;
use Vinkius\Vurb\Middleware\VurbMiddleware;
use Vinkius\Vurb\Presenters\VurbPresenter;
use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Tools\VurbTool;

/**
 * FakeVurbTester — executes the full MVA pipeline in PHP without a daemon.
 *
 * Usage:
 *   $tester = FakeVurbTester::for(GetCustomerProfile::class);
 *   $result = $tester->call(['id' => 42]);
 *   $result->assertSuccessful()->assertDataHasKey('name');
 */
class FakeVurbTester
{
    protected VurbTool $tool;
    protected ReflectionEngine $reflection;
    protected array $middleware = [];

    protected function __construct(string $toolClass)
    {
        $this->tool = App::make($toolClass);
        $this->reflection = App::make(ReflectionEngine::class);
        $this->middleware = $this->tool->middleware;
    }

    /**
     * Create a tester for a tool class.
     */
    public static function for(string $toolClass): static
    {
        return new static($toolClass);
    }

    /**
     * Override middleware for this test.
     */
    public function withMiddleware(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * Add middleware for this test.
     */
    public function addMiddleware(string ...$middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * Execute the tool with given input and return an MvaTestResult.
     */
    public function call(array $input = []): MvaTestResult
    {
        $startTime = hrtime(true);
        $toolName = $this->tool->name();

        try {
            // Validate input
            $validationError = $this->validateInput($input);
            if ($validationError !== null) {
                return new MvaTestResult(
                    isError: true,
                    errorCode: 'VALIDATION_ERROR',
                    errorMessage: $validationError,
                    data: null,
                    systemRules: [],
                    uiBlocks: [],
                    suggestActions: [],
                    latencyMs: (hrtime(true) - $startTime) / 1e6,
                    toolName: $toolName,
                );
            }

            // Run middleware pipeline
            $context = [
                'tool' => $toolName,
                'input' => $input,
                'request' => null,
                'user' => null,
            ];

            $result = $this->runMiddlewarePipeline($context, function () use ($input) {
                return $this->executeTool($input);
            });

            // If middleware returned an error array
            if (is_array($result) && ($result['error'] ?? false)) {
                return new MvaTestResult(
                    isError: true,
                    errorCode: $result['code'] ?? 'MIDDLEWARE_ERROR',
                    errorMessage: $result['message'] ?? 'Middleware returned error.',
                    data: $result,
                    systemRules: [],
                    uiBlocks: [],
                    suggestActions: [],
                    latencyMs: (hrtime(true) - $startTime) / 1e6,
                    toolName: $toolName,
                );
            }

            // Extract presenter metadata
            $systemRules = [];
            $uiBlocks = [];
            $suggestActions = [];

            if ($result instanceof VurbPresenter) {
                $systemRules = array_values(array_filter($result->systemRules()));
                $uiBlocks = $result->uiBlocks();
                $suggestActions = $result->suggestActions();
            }

            // Serialize data
            $data = $this->serializeResult($result);

            return new MvaTestResult(
                isError: false,
                errorCode: null,
                errorMessage: null,
                data: $data,
                systemRules: $systemRules,
                uiBlocks: $uiBlocks,
                suggestActions: $suggestActions,
                latencyMs: (hrtime(true) - $startTime) / 1e6,
                toolName: $toolName,
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return new MvaTestResult(
                isError: true,
                errorCode: 'NOT_FOUND',
                errorMessage: 'Resource not found.',
                data: null,
                systemRules: [],
                uiBlocks: [],
                suggestActions: [],
                latencyMs: (hrtime(true) - $startTime) / 1e6,
                toolName: $toolName,
            );
        } catch (\Throwable $e) {
            return new MvaTestResult(
                isError: true,
                errorCode: 'INTERNAL_ERROR',
                errorMessage: $e->getMessage(),
                data: null,
                systemRules: [],
                uiBlocks: [],
                suggestActions: [],
                latencyMs: (hrtime(true) - $startTime) / 1e6,
                toolName: $toolName,
            );
        }
    }

    /**
     * Get the reflected input schema for this tool.
     */
    public function getInputSchema(): array
    {
        return $this->reflection->reflectTool($this->tool)['inputSchema'] ?? [];
    }

    /**
     * Validate input against the tool's expected parameters.
     */
    protected function validateInput(array $input): ?string
    {
        $ref = new \ReflectionClass($this->tool);
        $method = $this->reflection->findHandleMethod($ref);

        if ($method === null) {
            return null;
        }

        foreach ($method->getParameters() as $param) {
            if ($this->reflection->isServiceContainerParam($param)) {
                continue;
            }

            $name = $param->getName();

            if (! array_key_exists($name, $input) && ! $param->isOptional() && ! $param->allowsNull()) {
                return "Missing required parameter: '{$name}'.";
            }
        }

        return null;
    }

    /**
     * Execute the tool with dependency injection.
     */
    protected function executeTool(array $input): mixed
    {
        $ref = new \ReflectionClass($this->tool);
        $method = $ref->getMethod('handle');
        $args = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($this->reflection->isServiceContainerParam($param)) {
                if ($type instanceof ReflectionNamedType) {
                    $args[] = App::make($type->getName());
                }
                continue;
            }

            if (array_key_exists($name, $input)) {
                $args[] = $input[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            }
        }

        return $method->invokeArgs($this->tool, $args);
    }

    /**
     * Run the middleware pipeline.
     */
    protected function runMiddlewarePipeline(array $context, \Closure $final): mixed
    {
        $pipeline = array_reverse($this->middleware);
        $next = $final;

        foreach ($pipeline as $middlewareClass) {
            $parameters = [];
            if (is_string($middlewareClass) && str_contains($middlewareClass, ':')) {
                [$middlewareClass, $paramStr] = explode(':', $middlewareClass, 2);
                $parameters = explode(',', $paramStr);
            }

            $instance = App::make($middlewareClass);

            if ($instance instanceof VurbMiddleware) {
                $currentNext = $next;
                $next = function (array $ctx) use ($instance, $currentNext, $parameters) {
                    if (! empty($parameters)) {
                        return $instance->handle($ctx, $currentNext, ...$parameters);
                    }
                    return $instance->handle($ctx, $currentNext);
                };
            }
        }

        return $next($context);
    }

    /**
     * Serialize the tool result.
     */
    protected function serializeResult(mixed $result): mixed
    {
        if ($result instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            return $result->resolve();
        }

        if ($result instanceof \Illuminate\Database\Eloquent\Model) {
            return $result->toArray();
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return $result;
    }
}
