<?php

namespace Vinkius\Vurb\Testing;

class MvaTestResult
{
    public function __construct(
        public readonly bool $isError,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly mixed $data,
        public readonly array $systemRules,
        public readonly array $uiBlocks,
        public readonly array $suggestActions,
        public readonly float $latencyMs,
        public readonly string $toolName,
    ) {}

    /**
     * Assert the result is not an error.
     */
    public function assertSuccessful(): static
    {
        if ($this->isError) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected successful result, got error: [{$this->errorCode}] {$this->errorMessage}"
            );
        }

        return $this;
    }

    /**
     * Assert the result is an error.
     */
    public function assertIsError(?string $code = null): static
    {
        if (! $this->isError) {
            throw new \PHPUnit\Framework\AssertionFailedError('Expected error result, got success.');
        }

        if ($code !== null && $this->errorCode !== $code) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected error code '{$code}', got '{$this->errorCode}'."
            );
        }

        return $this;
    }

    /**
     * Assert the data contains a key.
     */
    public function assertDataHasKey(string $key): static
    {
        if (! is_array($this->data) || ! array_key_exists($key, $this->data)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected data to have key '{$key}'."
            );
        }

        return $this;
    }

    /**
     * Assert the data does NOT contain a key (egress firewall test).
     */
    public function assertDataMissingKey(string $key): static
    {
        if (is_array($this->data) && array_key_exists($key, $this->data)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected data to NOT have key '{$key}' (egress firewall violation)."
            );
        }

        return $this;
    }

    /**
     * Assert the data value at a key.
     */
    public function assertDataEquals(string $key, mixed $expected): static
    {
        $this->assertDataHasKey($key);

        $actual = $this->data[$key];
        if ($actual !== $expected) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected data['{$key}'] to be " . var_export($expected, true) . ", got " . var_export($actual, true) . "."
            );
        }

        return $this;
    }

    /**
     * Assert system rules contain a specific rule.
     */
    public function assertHasSystemRule(string $rule): static
    {
        if (! in_array($rule, $this->systemRules, true)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected system rules to contain: '{$rule}'."
            );
        }

        return $this;
    }

    /**
     * Assert system rules are not empty.
     */
    public function assertHasSystemRules(): static
    {
        if (empty($this->systemRules)) {
            throw new \PHPUnit\Framework\AssertionFailedError('Expected system rules to be non-empty.');
        }

        return $this;
    }

    /**
     * Assert UI blocks are present.
     */
    public function assertHasUiBlocks(): static
    {
        if (empty($this->uiBlocks)) {
            throw new \PHPUnit\Framework\AssertionFailedError('Expected UI blocks to be non-empty.');
        }

        return $this;
    }

    /**
     * Assert suggested actions are present.
     */
    public function assertHasSuggestActions(): static
    {
        if (empty($this->suggestActions)) {
            throw new \PHPUnit\Framework\AssertionFailedError('Expected suggest actions to be non-empty.');
        }

        return $this;
    }

    /**
     * Assert a specific tool is suggested.
     */
    public function assertSuggestsTool(string $toolName): static
    {
        foreach ($this->suggestActions as $action) {
            if (($action['tool'] ?? '') === $toolName) {
                return $this;
            }
        }

        throw new \PHPUnit\Framework\AssertionFailedError(
            "Expected suggested actions to include tool '{$toolName}'."
        );
    }

    /**
     * Get the tool execution latency in milliseconds.
     */
    public function latency(): float
    {
        return $this->latencyMs;
    }
}
