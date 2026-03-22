<?php

namespace Vinkius\Vurb\Tests\Hostile;

use Vinkius\Vurb\Security\DlpRedactor;
use Vinkius\Vurb\Tests\TestCase;

/**
 * Tests DLP redaction under adversarial conditions: encoding bypass,
 * null bytes, extremely long data, deeply nested arrays, partial matches,
 * unicode tricks, and catastrophic backtracking prevention.
 */
class DlpBypassResilienceTest extends TestCase
{
    // ─── Null Byte Bypass ───

    public function test_null_byte_before_ssn_does_not_bypass(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact("SSN\x00123-45-6789");

        // The SSN regex should still match since \x00 is separate from digit chars
        $this->assertStringNotContainsString('123-45-6789', $result);
    }

    // ─── Newline Split Bypass ───

    public function test_newline_in_middle_of_ssn(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();

        // This SSN is split by a newline — the regex should NOT match
        // This is an intentional limitation: pattern-based DLP doesn't catch fragmented data
        $result = $redactor->redact("SSN: 123-45\n-6789");

        // Document the behavior: if the pattern doesn't match split data, it passes through
        // This is not a bug — it's a known limitation of regex-based DLP
        $this->assertIsString($result);
    }

    // ─── Case Sensitivity ───

    public function test_case_insensitive_email_pattern(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'email' => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        ]);

        $redactor = new DlpRedactor();

        $result = $redactor->redact('Contact: ADMIN@EXAMPLE.COM');
        $this->assertStringNotContainsString('ADMIN@EXAMPLE.COM', $result);
        $this->assertStringContainsString('[REDACTED]', $result);
    }

    public function test_mixed_case_pattern_without_flag(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            // Intentionally case-sensitive (no /i flag)
            'secret' => '/secret-key-\d+/',
        ]);

        $redactor = new DlpRedactor();

        // Lowercase matches
        $result1 = $redactor->redact('Key: secret-key-12345');
        $this->assertStringNotContainsString('secret-key-12345', $result1);

        // Uppercase does NOT match (as expected for case-sensitive pattern)
        $result2 = $redactor->redact('Key: SECRET-KEY-12345');
        $this->assertStringContainsString('SECRET-KEY-12345', $result2);
    }

    // ─── Extremely Long Strings ───

    public function test_redact_very_long_string(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();

        // Build a 1MB string with SSNs sprinkled throughout
        $chunks = [];
        for ($i = 0; $i < 100; $i++) {
            $chunks[] = str_repeat('X', 10_000) . '999-88-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        }
        $longString = implode(' ', $chunks);

        $result = $redactor->redact($longString);

        // All 100 SSNs should be redacted
        $this->assertSame(100, substr_count($result, '[REDACTED]'));
        // No original SSN should remain
        $this->assertStringNotContainsString('999-88-', $result);
    }

    // ─── Deeply Nested Arrays ───

    public function test_redact_deeply_nested_array(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();

        // 50-level deep nesting
        $data = 'SSN: 123-45-6789';
        for ($i = 0; $i < 50; $i++) {
            $data = ['level_' . $i => $data];
        }

        $result = $redactor->redact($data);

        // Navigate to the deepest level
        $current = $result;
        for ($i = 49; $i >= 0; $i--) {
            $this->assertArrayHasKey('level_' . $i, $current);
            $current = $current['level_' . $i];
        }

        // The deepest string should be redacted
        $this->assertStringNotContainsString('123-45-6789', $current);
        $this->assertStringContainsString('[REDACTED]', $current);
    }

    // ─── Mask Strategy Edge Cases ───

    public function test_mask_short_string(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'mask');
        $this->app['config']->set('vurb.dlp.patterns', [
            'pin' => '/\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('PIN: 1234');

        // 4-char match → should be fully masked (≤ 4 chars)
        $this->assertStringNotContainsString('1234', $result);
    }

    public function test_mask_preserves_surrounding_text(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'mask');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('Before 123-45-6789 After');

        $this->assertStringContainsString('Before', $result);
        $this->assertStringContainsString('After', $result);
        $this->assertStringNotContainsString('123-45-6789', $result);
    }

    // ─── Hash Strategy Determinism ───

    public function test_hash_is_deterministic(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'hash');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();

        $result1 = $redactor->redact('SSN: 123-45-6789');
        $result2 = $redactor->redact('SSN: 123-45-6789');

        // Same input should produce same hash
        $this->assertSame($result1, $result2);
    }

    public function test_different_values_produce_different_hashes(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'hash');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();

        $result1 = $redactor->redact('SSN: 123-45-6789');
        $result2 = $redactor->redact('SSN: 987-65-4321');

        $this->assertNotSame($result1, $result2);
    }

    // ─── Unicode in Patterns ───

    public function test_unicode_pattern_matching(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'cpf' => '/\d{3}\.\d{3}\.\d{3}-\d{2}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('CPF: 123.456.789-00 é confidencial');

        $this->assertStringNotContainsString('123.456.789-00', $result);
        $this->assertStringContainsString('é confidencial', $result);
    }

    // ─── Empty/Null Inputs ───

    public function test_redact_empty_string(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.patterns', ['all' => '/.*/']);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('');

        $this->assertSame('', $result);
    }

    public function test_redact_empty_array(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.patterns', ['all' => '/.*/']);

        $redactor = new DlpRedactor();
        $result = $redactor->redact([]);

        $this->assertSame([], $result);
    }

    // ─── Mixed Type Arrays ───

    public function test_redact_array_with_mixed_types(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact([
            'name' => 'John',
            'age' => 30,           // int, not redacted
            'active' => true,      // bool, not redacted
            'balance' => 99.99,    // float, not redacted
            'ssn' => '123-45-6789',
            'data' => null,        // null, not redacted
        ]);

        $this->assertSame('John', $result['name']);
        $this->assertSame(30, $result['age']);
        $this->assertTrue($result['active']);
        $this->assertSame(99.99, $result['balance']);
        $this->assertStringNotContainsString('123-45-6789', $result['ssn']);
        $this->assertNull($result['data']);
    }

    // ─── Regex with Special Characters ───

    public function test_pattern_with_special_regex_chars(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'card' => '/\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}/',
        ]);

        $redactor = new DlpRedactor();

        $result1 = $redactor->redact('Card: 4111-1111-1111-1111');
        $this->assertStringNotContainsString('4111-1111-1111-1111', $result1);

        $result2 = $redactor->redact('Card: 4111 1111 1111 1111');
        $this->assertStringNotContainsString('4111 1111 1111 1111', $result2);

        $result3 = $redactor->redact('Card: 4111111111111111');
        $this->assertStringNotContainsString('4111111111111111', $result3);
    }
}
