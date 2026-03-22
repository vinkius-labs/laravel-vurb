<?php

namespace Vinkius\Vurb\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ReflectionMethod;
use ReflectionNamedType;
use Vinkius\Vurb\Events\ToolExecuted;
use Vinkius\Vurb\Events\ToolFailed;
use Vinkius\Vurb\Exceptions\ToolNotFoundException;
use Vinkius\Vurb\Middleware\VurbMiddleware;
use Vinkius\Vurb\Presenters\PresenterRegistry;
use Vinkius\Vurb\Presenters\VurbPresenter;
use Vinkius\Vurb\Services\ReflectionEngine;
use Vinkius\Vurb\Services\ToolDiscovery;

class VurbBridgeController extends Controller
{
    public function __construct(
        protected ToolDiscovery $discovery,
        protected ReflectionEngine $reflection,
        protected PresenterRegistry $presenterRegistry,
    ) {}

    /**
     * Execute a tool action.
     * POST /_vurb/execute/{toolName}/handle
     */
    public function execute(Request $request, string $toolName): JsonResponse
    {
        $startTime = hrtime(true);
        $requestId = $request->header('X-Vurb-Request-Id', bin2hex(random_bytes(8)));

        try {
            $entry = $this->discovery->findTool($toolName);

            if ($entry === null) {
                throw new ToolNotFoundException("Tool '{$toolName}' not found.");
            }

            $toolInstance = $entry['tool'];
            $input = $request->all();

            // Run PHP middleware pipeline
            $result = $this->runMiddlewarePipeline(
                $entry['middleware'],
                [
                    'tool' => $toolName,
                    'input' => $input,
                    'request' => $request,
                    'user' => $request->user(),
                ],
                function (array $context) use ($toolInstance, $input) {
                    return $this->executeTool($toolInstance, $input);
                }
            );

            $latencyMs = (hrtime(true) - $startTime) / 1e6;

            // Process presenter metadata
            $presenterMeta = $this->extractPresenterMeta($result);
            $data = $this->serializeResult($result);

            // Apply DLP redaction if enabled
            $dlp = app(\Vinkius\Vurb\Security\DlpRedactor::class);
            if ($dlp->isEnabled()) {
                $data = $dlp->redact($data);
            }

            $response = [
                'data' => $data,
                'meta' => [
                    'request_id' => $requestId,
                    'latency_ms' => round($latencyMs, 2),
                    'tool' => $toolName,
                ],
            ];

            // Flatten presenter metadata to top level for vurb.ts daemon compatibility
            if (! empty($presenterMeta)) {
                $response = array_merge($response, $presenterMeta);
            }

            if (config('vurb.observability.events', true)) {
                event(new ToolExecuted(
                    toolName: $toolName,
                    input: $input,
                    latencyMs: round($latencyMs, 2),
                    presenterName: $presenterMeta ? class_basename(get_class($result)) : null,
                    systemRules: $presenterMeta['systemRules'] ?? [],
                    isError: false,
                ));
            }

            return response()->json($response);

        } catch (ToolNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 'NOT_FOUND', 404, $requestId, $startTime);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse(
                'Resource not found.',
                'NOT_FOUND',
                404,
                $requestId,
                $startTime,
                ['model' => class_basename($e->getModel()), 'ids' => $e->getIds()],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed.',
                'VALIDATION_ERROR',
                422,
                $requestId,
                $startTime,
                ['errors' => $e->errors()],
            );
        } catch (\Throwable $e) {
            $latencyMs = (hrtime(true) - $startTime) / 1e6;

            if (config('vurb.observability.events', true)) {
                event(new ToolFailed(
                    toolName: $toolName,
                    input: $request->all(),
                    error: $e->getMessage(),
                    latencyMs: round($latencyMs, 2),
                ));
            }

            return $this->errorResponse(
                app()->hasDebugModeEnabled() ? $e->getMessage() : 'Internal server error.',
                'INTERNAL_ERROR',
                500,
                $requestId,
                $startTime,
            );
        }
    }

    /**
     * Refresh the schema manifest.
     * POST /_vurb/schema/refresh
     */
    public function refreshSchema(Request $request): JsonResponse
    {
        $compiler = app(\Vinkius\Vurb\Services\ManifestCompiler::class);
        $path = $compiler->compileAndWrite();

        return response()->json([
            'status' => 'ok',
            'path' => $path,
        ]);
    }

    /**
     * FSM state transition.
     * POST /_vurb/state/transition
     */
    public function fsmTransition(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');
        $event = $request->input('event');

        $fsmConfig = config('vurb.fsm');
        if ($fsmConfig === null) {
            return response()->json(['error' => true, 'code' => 'FSM_DISABLED', 'message' => 'FSM is not configured.'], 400);
        }

        $store = app(\Vinkius\Vurb\Fsm\FsmStateStore::class);
        $current = $store->getCurrentState($sessionId, $fsmConfig['id']);
        $states = $fsmConfig['states'] ?? [];

        if (! isset($states[$current]['on'][$event])) {
            return response()->json([
                'error' => true,
                'code' => 'INVALID_TRANSITION',
                'message' => "Event '{$event}' is not valid in state '{$current}'.",
                'currentState' => $current,
                'validEvents' => array_keys($states[$current]['on'] ?? []),
            ], 422);
        }

        $nextState = $states[$current]['on'][$event];
        $store->setState($sessionId, $fsmConfig['id'], $nextState);

        if (config('vurb.observability.events', true)) {
            event(new \Vinkius\Vurb\Events\StateInvalidated(
                pattern: 'fsm.' . $fsmConfig['id'],
                trigger: $event,
            ));
        }

        return response()->json([
            'previousState' => $current,
            'currentState' => $nextState,
            'event' => $event,
        ]);
    }

    /**
     * Health check endpoint.
     * GET /_vurb/health
     */
    public function health(Request $request): JsonResponse
    {
        $tools = $this->discovery->discover();

        return response()->json([
            'status' => 'ok',
            'tools_count' => count($tools),
            'server' => config('vurb.server.name'),
            'version' => config('vurb.server.version'),
        ]);
    }

    /**
     * Execute a tool instance with dependency injection.
     */
    protected function executeTool(object $tool, array $input): mixed
    {
        $ref = new \ReflectionClass($tool);
        $method = $ref->getMethod('handle');
        $args = $this->resolveArguments($method, $input);

        return $method->invokeArgs($tool, $args);
    }

    /**
     * Resolve method arguments from input + service container.
     */
    protected function resolveArguments(ReflectionMethod $method, array $input): array
    {
        $args = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($this->reflection->isServiceContainerParam($param)) {
                // Resolve from service container
                if ($type instanceof ReflectionNamedType) {
                    $args[] = app($type->getName());
                }
                continue;
            }

            // Resolve from input
            if (array_key_exists($name, $input)) {
                $args[] = $this->castValue($input[$name], $type);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], [$name => 'required'])
                );
            }
        }

        return $args;
    }

    /**
     * Cast a value to the expected PHP type.
     */
    protected function castValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($type === null || ! $type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'array' => (array) $value,
            default => $value,
        };
    }

    /**
     * Run the PHP middleware pipeline.
     */
    protected function runMiddlewarePipeline(array $middleware, array $context, \Closure $final): mixed
    {
        $pipeline = array_reverse($middleware);

        $next = $final;

        foreach ($pipeline as $middlewareClass) {
            // Support middleware with parameters: Class:param1,param2
            $parameters = [];
            if (is_string($middlewareClass) && str_contains($middlewareClass, ':')) {
                [$middlewareClass, $paramStr] = explode(':', $middlewareClass, 2);
                $parameters = explode(',', $paramStr);
            }

            $instance = app($middlewareClass);

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
     * Extract presenter metadata from the result.
     */
    protected function extractPresenterMeta(mixed $result): array
    {
        if (! $result instanceof VurbPresenter) {
            return [];
        }

        $meta = [];

        $systemRules = $result->systemRules();
        if (! empty($systemRules)) {
            $meta['systemRules'] = array_values(array_filter($systemRules));
        }

        $uiBlocks = $result->uiBlocks();
        if (! empty($uiBlocks)) {
            $meta['uiBlocks'] = $uiBlocks;
        }

        $suggestActions = $result->suggestActions();
        if (! empty($suggestActions)) {
            $meta['suggestActions'] = $suggestActions;
        }

        return $meta;
    }

    /**
     * Serialize a result into array data.
     */
    protected function serializeResult(mixed $result): mixed
    {
        if ($result instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            return $result->resolve();
        }

        if ($result instanceof \Illuminate\Database\Eloquent\Model) {
            return $result->toArray();
        }

        if ($result instanceof \Illuminate\Support\Collection) {
            return $result->toArray();
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return $result;
    }

    /**
     * Build a standard error response.
     */
    protected function errorResponse(
        string $message,
        string $code,
        int $status,
        string $requestId,
        int $startTime,
        array $details = [],
    ): JsonResponse {
        $latencyMs = (hrtime(true) - $startTime) / 1e6;

        $response = [
            'error' => true,
            'code' => $code,
            'message' => $message,
            'meta' => [
                'request_id' => $requestId,
                'latency_ms' => round($latencyMs, 2),
            ],
        ];

        if (! empty($details)) {
            $response['details'] = $details;
        }

        return response()->json($response, $status);
    }
}
