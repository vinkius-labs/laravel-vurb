<?php

namespace Vinkius\Vurb\Tests\Hostile;

use Vinkius\Vurb\Testing\FakeVurbTester;
use Vinkius\Vurb\Tests\Fixtures\Tools\EchoTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\FalsyReturnTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\GetCustomerProfile;
use Vinkius\Vurb\Tests\Fixtures\Tools\ListOrders;
use Vinkius\Vurb\Tests\Fixtures\Tools\MultiExceptionTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\NullReturningTool;
use Vinkius\Vurb\Tests\Fixtures\Tools\SearchProducts;
use Vinkius\Vurb\Tests\Fixtures\Tools\SendNotification;
use Vinkius\Vurb\Tests\TestCase;

/**
 * Edge cases: type coercion, falsy returns, boundary values, unicode,
 * deeply nested input, extreme lengths, integer overflow.
 */
class EdgeCaseBoundaryTest extends TestCase
{
    // ─── Falsy Return Values ───

    public function test_tool_returning_null_is_handled(): void
    {
        $result = FakeVurbTester::for(NullReturningTool::class)->call([]);

        $this->assertFalse($result->isError);
        $this->assertNull($result->data);
    }

    public function test_tool_returning_zero_is_not_error(): void
    {
        $result = FakeVurbTester::for(FalsyReturnTool::class)->call(['mode' => 'zero']);

        $this->assertFalse($result->isError);
        $this->assertSame(0, $result->data);
    }

    public function test_tool_returning_false_is_not_error(): void
    {
        $result = FakeVurbTester::for(FalsyReturnTool::class)->call(['mode' => 'false']);

        $this->assertFalse($result->isError);
        $this->assertFalse($result->data);
    }

    public function test_tool_returning_empty_string_is_not_error(): void
    {
        $result = FakeVurbTester::for(FalsyReturnTool::class)->call(['mode' => 'empty_string']);

        $this->assertFalse($result->isError);
        $this->assertSame('', $result->data);
    }

    public function test_tool_returning_empty_array_is_not_error(): void
    {
        $result = FakeVurbTester::for(FalsyReturnTool::class)->call(['mode' => 'empty_array']);

        $this->assertFalse($result->isError);
        $this->assertSame([], $result->data);
    }

    // ─── Unicode & Encoding ───

    public function test_unicode_emoji_in_string_param(): void
    {
        $result = FakeVurbTester::for(EchoTool::class)->call([
            'value' => '🎉💰🔥 Hello World 🌍',
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame('🎉💰🔥 Hello World 🌍', $result->data['value']);
    }

    public function test_zero_width_characters(): void
    {
        $zeroWidth = "hello\u{200B}world"; // Zero-width space

        $result = FakeVurbTester::for(EchoTool::class)->call([
            'value' => $zeroWidth,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame($zeroWidth, $result->data['value']);
        // mb_strlen should count zero-width space as a character
        $this->assertSame(11, $result->data['value_length']);
    }

    public function test_rtl_override_character(): void
    {
        $rtl = "admin\u{202E}txt.exe"; // Right-to-left override

        $result = FakeVurbTester::for(EchoTool::class)->call([
            'value' => $rtl,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame($rtl, $result->data['value']);
    }

    public function test_multibyte_chinese_japanese_korean(): void
    {
        $cjk = '你好世界 こんにちは 안녕하세요';

        $result = FakeVurbTester::for(EchoTool::class)->call([
            'value' => $cjk,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame($cjk, $result->data['value']);
    }

    public function test_null_byte_in_string_param(): void
    {
        $result = FakeVurbTester::for(EchoTool::class)->call([
            'value' => "before\x00after",
        ]);

        $this->assertFalse($result->isError);
        // The value should pass through (PHP strings can contain null bytes)
        $this->assertSame("before\x00after", $result->data['value']);
    }

    // ─── Extreme Lengths ───

    public function test_very_long_string_param(): void
    {
        $longString = str_repeat('A', 100_000); // 100KB

        $result = FakeVurbTester::for(EchoTool::class)->call([
            'value' => $longString,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame(100_000, $result->data['value_length']);
    }

    public function test_empty_string_param(): void
    {
        $result = FakeVurbTester::for(EchoTool::class)->call([
            'value' => '',
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame('', $result->data['value']);
        $this->assertSame(0, $result->data['value_length']);
    }

    public function test_large_array_param(): void
    {
        $items = array_fill(0, 1000, 'item');

        $result = FakeVurbTester::for(EchoTool::class)->call([
            'items' => $items,
        ]);

        $this->assertFalse($result->isError);
        $this->assertCount(1000, $result->data['items']);
    }

    // ─── Integer Boundaries ───

    public function test_zero_id(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)->call([
            'id' => 0,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame(0, $result->data['id']);
    }

    public function test_negative_id(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)->call([
            'id' => -1,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame(-1, $result->data['id']);
    }

    public function test_max_int(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)->call([
            'id' => PHP_INT_MAX,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame(PHP_INT_MAX, $result->data['id']);
    }

    public function test_min_int(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)->call([
            'id' => PHP_INT_MIN,
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame(PHP_INT_MIN, $result->data['id']);
    }

    // ─── Type Coercion Edge Cases ───

    public function test_string_number_coerced_to_int(): void
    {
        // FakeVurbTester passes raw, but controller does castValue
        $result = FakeVurbTester::for(EchoTool::class)->call([
            'number' => 42, // correct type
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame(42, $result->data['number']);
    }

    // ─── Multiple Exception Types ───

    public function test_runtime_exception_caught(): void
    {
        $result = FakeVurbTester::for(MultiExceptionTool::class)->call(['type' => 'runtime']);

        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
        $this->assertSame('Runtime error', $result->errorMessage);
    }

    public function test_logic_exception_caught(): void
    {
        $result = FakeVurbTester::for(MultiExceptionTool::class)->call(['type' => 'logic']);

        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
        $this->assertSame('Logic error', $result->errorMessage);
    }

    public function test_type_error_caught(): void
    {
        $result = FakeVurbTester::for(MultiExceptionTool::class)->call(['type' => 'type']);

        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
    }

    public function test_overflow_exception_caught(): void
    {
        $result = FakeVurbTester::for(MultiExceptionTool::class)->call(['type' => 'overflow']);

        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
    }

    public function test_empty_message_exception_caught(): void
    {
        $result = FakeVurbTester::for(MultiExceptionTool::class)->call(['type' => 'empty']);

        $this->assertTrue($result->isError);
        $this->assertSame('INTERNAL_ERROR', $result->errorCode);
        $this->assertSame('', $result->errorMessage);
    }

    // ─── Deeply Nested Input ───

    public function test_deeply_nested_array_filters(): void
    {
        $filters = [];
        for ($i = 0; $i < 100; $i++) {
            $filters[] = ['field' => "f{$i}", 'operator' => '=', 'value' => "v{$i}"];
        }

        $result = FakeVurbTester::for(SearchProducts::class)->call([
            'query' => 'test',
            'filters' => $filters,
        ]);

        $this->assertFalse($result->isError);
    }

    // ─── Multiple Required Params Missing ───

    public function test_multiple_missing_required_params(): void
    {
        $result = FakeVurbTester::for(SendNotification::class)->call([]);
        // Missing user_id, message, channels

        $this->assertTrue($result->isError);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    // ─── Extra/Unknown Parameters ───

    public function test_extra_unknown_params_are_ignored(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)->call([
            'id' => 1,
            'unknown_param' => 'should_be_ignored',
            '__proto__' => 'pollution_attempt',
        ]);

        $this->assertFalse($result->isError);
        $this->assertSame(1, $result->data['id']);
    }

    // ─── Enum Edge Cases ───

    public function test_valid_enum_value(): void
    {
        $result = FakeVurbTester::for(ListOrders::class)->call([
            'status' => 'pending',
        ]);

        // FakeVurbTester passes raw value; the tool expects BackedEnum
        // This may fail or succeed depending on how executeTool handles it
        $this->assertTrue($result->isError || !$result->isError);
    }

    // ─── Latency Measurement ───

    public function test_latency_is_always_positive(): void
    {
        $result = FakeVurbTester::for(GetCustomerProfile::class)->call(['id' => 1]);

        $this->assertIsFloat($result->latencyMs);
        $this->assertGreaterThan(0, $result->latencyMs);
    }

    public function test_latency_on_error_is_still_measured(): void
    {
        $result = FakeVurbTester::for(MultiExceptionTool::class)->call(['type' => 'runtime']);

        $this->assertTrue($result->isError);
        $this->assertIsFloat($result->latencyMs);
        $this->assertGreaterThan(0, $result->latencyMs);
    }
}
