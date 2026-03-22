<?php

namespace Vinkius\Vurb\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use Vinkius\Vurb\Testing\MvaTestResult;
use Vinkius\Vurb\Tests\TestCase;

class MvaTestResultTest extends TestCase
{
    protected function makeResult(array $overrides = []): MvaTestResult
    {
        return new MvaTestResult(
            isError: $overrides['isError'] ?? false,
            errorCode: $overrides['errorCode'] ?? null,
            errorMessage: $overrides['errorMessage'] ?? null,
            data: $overrides['data'] ?? ['name' => 'John'],
            systemRules: $overrides['systemRules'] ?? [],
            uiBlocks: $overrides['uiBlocks'] ?? [],
            suggestActions: $overrides['suggestActions'] ?? [],
            latencyMs: $overrides['latencyMs'] ?? 10.0,
            toolName: $overrides['toolName'] ?? 'test-tool',
        );
    }

    // ─── assertHasSystemRule ───

    public function test_assert_has_system_rule_passes(): void
    {
        $result = $this->makeResult(['systemRules' => ['Be polite', 'Use formal tone']]);

        $returned = $result->assertHasSystemRule('Be polite');
        $this->assertSame($result, $returned); // fluent
    }

    public function test_assert_has_system_rule_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Be polite');

        $result = $this->makeResult(['systemRules' => ['Other rule']]);
        $result->assertHasSystemRule('Be polite');
    }

    // ─── assertHasSystemRules ───

    public function test_assert_has_system_rules_passes_when_non_empty(): void
    {
        $result = $this->makeResult(['systemRules' => ['Something']]);
        $returned = $result->assertHasSystemRules();
        $this->assertSame($result, $returned);
    }

    public function test_assert_has_system_rules_fails_when_empty(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('non-empty');

        $result = $this->makeResult(['systemRules' => []]);
        $result->assertHasSystemRules();
    }

    // ─── assertHasUiBlocks ───

    public function test_assert_has_ui_blocks_passes(): void
    {
        $result = $this->makeResult(['uiBlocks' => [['type' => 'card']]]);
        $returned = $result->assertHasUiBlocks();
        $this->assertSame($result, $returned);
    }

    public function test_assert_has_ui_blocks_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('UI blocks');

        $result = $this->makeResult(['uiBlocks' => []]);
        $result->assertHasUiBlocks();
    }

    // ─── assertHasSuggestActions ───

    public function test_assert_has_suggest_actions_passes(): void
    {
        $result = $this->makeResult(['suggestActions' => [['tool' => 'edit-customer']]]);
        $returned = $result->assertHasSuggestActions();
        $this->assertSame($result, $returned);
    }

    public function test_assert_has_suggest_actions_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('suggest actions');

        $result = $this->makeResult(['suggestActions' => []]);
        $result->assertHasSuggestActions();
    }

    // ─── assertSuggestsTool ───

    public function test_assert_suggests_tool_passes(): void
    {
        $result = $this->makeResult(['suggestActions' => [['tool' => 'edit-customer'], ['tool' => 'delete-customer']]]);
        $returned = $result->assertSuggestsTool('edit-customer');
        $this->assertSame($result, $returned);
    }

    public function test_assert_suggests_tool_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('edit-customer');

        $result = $this->makeResult(['suggestActions' => [['tool' => 'other-tool']]]);
        $result->assertSuggestsTool('edit-customer');
    }

    public function test_assert_suggests_tool_fails_on_empty(): void
    {
        $this->expectException(AssertionFailedError::class);

        $result = $this->makeResult(['suggestActions' => []]);
        $result->assertSuggestsTool('any-tool');
    }

    // ─── Verify existing assertions still work (regression) ───

    public function test_assert_successful_passes(): void
    {
        $result = $this->makeResult(['isError' => false]);
        $this->assertSame($result, $result->assertSuccessful());
    }

    public function test_assert_successful_fails(): void
    {
        $this->expectException(AssertionFailedError::class);

        $result = $this->makeResult(['isError' => true, 'errorCode' => 'FAIL', 'errorMessage' => 'broke']);
        $result->assertSuccessful();
    }

    public function test_assert_is_error_passes(): void
    {
        $result = $this->makeResult(['isError' => true, 'errorCode' => 'NOT_FOUND']);
        $this->assertSame($result, $result->assertIsError('NOT_FOUND'));
    }

    public function test_assert_is_error_wrong_code(): void
    {
        $this->expectException(AssertionFailedError::class);

        $result = $this->makeResult(['isError' => true, 'errorCode' => 'OTHER']);
        $result->assertIsError('NOT_FOUND');
    }

    public function test_assert_data_has_key(): void
    {
        $result = $this->makeResult(['data' => ['x' => 1]]);
        $this->assertSame($result, $result->assertDataHasKey('x'));
    }

    public function test_assert_data_missing_key(): void
    {
        $result = $this->makeResult(['data' => ['x' => 1]]);
        $this->assertSame($result, $result->assertDataMissingKey('y'));
    }

    public function test_assert_data_equals(): void
    {
        $result = $this->makeResult(['data' => ['x' => 42]]);
        $this->assertSame($result, $result->assertDataEquals('x', 42));
    }

    public function test_chained_assertions(): void
    {
        $result = $this->makeResult([
            'data' => ['id' => 1],
            'systemRules' => ['Be polite'],
            'uiBlocks' => [['type' => 'card']],
            'suggestActions' => [['tool' => 'edit']],
        ]);

        $result
            ->assertSuccessful()
            ->assertDataHasKey('id')
            ->assertDataEquals('id', 1)
            ->assertHasSystemRule('Be polite')
            ->assertHasSystemRules()
            ->assertHasUiBlocks()
            ->assertHasSuggestActions()
            ->assertSuggestsTool('edit');

        // MvaTestResult uses throw-based assertions, PHPUnit needs at least one tracked assertion
        $this->addToAssertionCount(8);
    }
}
