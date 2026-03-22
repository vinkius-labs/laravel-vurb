<?php

namespace Vinkius\Vurb\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use Vinkius\Vurb\Testing\MvaTestResult;
use Vinkius\Vurb\Tests\TestCase;

class MvaTestResultExtendedTest extends TestCase
{
    // --- assertIsError() passes when error ---

    public function test_assert_is_error_passes_when_error(): void
    {
        $result = new MvaTestResult(
            isError: true,
            errorCode: 'INTERNAL_ERROR',
            errorMessage: 'Something broke',
            data: null,
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $returned = $result->assertIsError();
        $this->assertSame($result, $returned); // fluent
    }

    public function test_assert_is_error_with_matching_code(): void
    {
        $result = new MvaTestResult(
            isError: true,
            errorCode: 'VALIDATION_ERROR',
            errorMessage: 'Missing required field',
            data: null,
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $result->assertIsError('VALIDATION_ERROR');
        $this->assertTrue(true); // no exception thrown
    }

    // --- assertIsError() fails when success ---

    public function test_assert_is_error_fails_when_success(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['key' => 'value'],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected error result, got success.');
        $result->assertIsError();
    }

    // --- assertIsError() with wrong code ---

    public function test_assert_is_error_fails_with_wrong_code(): void
    {
        $result = new MvaTestResult(
            isError: true,
            errorCode: 'INTERNAL_ERROR',
            errorMessage: 'Error',
            data: null,
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Expected error code 'VALIDATION_ERROR'");
        $result->assertIsError('VALIDATION_ERROR');
    }

    // --- assertDataMissingKey() passes when key absent ---

    public function test_assert_data_missing_key_passes(): void
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

        $returned = $result->assertDataMissingKey('email');
        $this->assertSame($result, $returned);
    }

    public function test_assert_data_missing_key_passes_when_data_is_null(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: null,
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $result->assertDataMissingKey('anything');
        $this->assertTrue(true);
    }

    // --- assertDataMissingKey() fails when key exists ---

    public function test_assert_data_missing_key_fails_when_key_exists(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['ssn' => '123-45-6789'],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("NOT have key 'ssn'");
        $result->assertDataMissingKey('ssn');
    }

    // --- assertHasSuggestActions() fails when empty ---

    public function test_assert_has_suggest_actions_fails_when_empty(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['id' => 1],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected suggest actions to be non-empty.');
        $result->assertHasSuggestActions();
    }

    // --- assertHasSuggestActions() passes when non-empty ---

    public function test_assert_has_suggest_actions_passes_when_non_empty(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['id' => 1],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [['tool' => 'orders.list', 'reason' => 'View orders']],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $result->assertHasSuggestActions();
        $this->assertTrue(true);
    }

    // --- assertSuccessful ---

    public function test_assert_successful_passes(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $returned = $result->assertSuccessful();
        $this->assertSame($result, $returned);
    }

    public function test_assert_successful_fails_when_error(): void
    {
        $result = new MvaTestResult(
            isError: true,
            errorCode: 'FAIL',
            errorMessage: 'it failed',
            data: null,
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $result->assertSuccessful();
    }

    // --- assertSuggestsTool ---

    public function test_assert_suggests_tool_passes(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [['tool' => 'customers.update', 'reason' => 'Edit']],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $result->assertSuggestsTool('customers.update');
        $this->assertTrue(true);
    }

    public function test_assert_suggests_tool_fails_when_not_suggested(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $result->assertSuggestsTool('whatever');
    }

    // --- assertDataEquals ---

    public function test_assert_data_equals_passes_on_match(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['count' => 5],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $returned = $result->assertDataEquals('count', 5);
        $this->assertSame($result, $returned);
    }

    public function test_assert_data_equals_fails_on_mismatch(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: ['count' => 5],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $result->assertDataEquals('count', 10);
    }

    // --- assertHasSystemRule ---

    public function test_assert_has_system_rule_passes(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: ['Never share PII', 'Be formal'],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $returned = $result->assertHasSystemRule('Never share PII');
        $this->assertSame($result, $returned);
    }

    public function test_assert_has_system_rule_fails(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: ['Be formal'],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $result->assertHasSystemRule('Never share PII');
    }

    // --- assertHasSystemRules ---

    public function test_assert_has_system_rules_passes(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: ['rule1'],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $returned = $result->assertHasSystemRules();
        $this->assertSame($result, $returned);
    }

    public function test_assert_has_system_rules_fails_when_empty(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $result->assertHasSystemRules();
    }

    // --- assertHasUiBlocks ---

    public function test_assert_has_ui_blocks_passes(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: [],
            uiBlocks: [['type' => 'chart', 'data' => []]],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $returned = $result->assertHasUiBlocks();
        $this->assertSame($result, $returned);
    }

    public function test_assert_has_ui_blocks_fails_when_empty(): void
    {
        $result = new MvaTestResult(
            isError: false,
            errorCode: null,
            errorMessage: null,
            data: [],
            systemRules: [],
            uiBlocks: [],
            suggestActions: [],
            latencyMs: 1.0,
            toolName: 'test.tool',
        );

        $this->expectException(AssertionFailedError::class);
        $result->assertHasUiBlocks();
    }
}
