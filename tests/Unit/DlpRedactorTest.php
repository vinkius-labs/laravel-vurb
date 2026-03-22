<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Security\DlpRedactor;
use Vinkius\Vurb\Tests\TestCase;

class DlpRedactorTest extends TestCase
{
    // --- Disabled mode ---

    public function test_redact_returns_original_when_disabled(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', false);

        $redactor = new DlpRedactor();
        $input = ['ssn' => '123-45-6789', 'name' => 'John'];

        $this->assertSame($input, $redactor->redact($input));
    }

    public function test_is_enabled_returns_false_when_disabled(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', false);

        $redactor = new DlpRedactor();
        $this->assertFalse($redactor->isEnabled());
    }

    // --- Mask strategy ---

    public function test_redact_string_with_mask_strategy(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'mask');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('My SSN is 123-45-6789 please');

        // Should not contain the original SSN
        $this->assertStringNotContainsString('123-45-6789', $result);
        // Should still contain surrounding text
        $this->assertStringContainsString('My SSN is', $result);
    }

    public function test_redact_array_with_mask_strategy(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'mask');
        $this->app['config']->set('vurb.dlp.patterns', [
            'email' => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertSame('John Doe', $result['name']);
        $this->assertStringNotContainsString('john@example.com', $result['email']);
    }

    // --- Remove strategy ---

    public function test_redact_with_remove_strategy(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('SSN: 123-45-6789');

        $this->assertStringContainsString('[REDACTED]', $result);
        $this->assertStringNotContainsString('123-45-6789', $result);
    }

    // --- Hash strategy ---

    public function test_redact_with_hash_strategy(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'hash');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('SSN: 123-45-6789');

        $this->assertStringContainsString('[HASH:', $result);
        $this->assertStringNotContainsString('123-45-6789', $result);
    }

    // --- Nested arrays ---

    public function test_redact_nested_arrays(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'phone' => '/\(\d{3}\)\s?\d{3}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact([
            'user' => [
                'name' => 'Jane',
                'phone' => 'Call me at (555) 123-4567',
            ],
        ]);

        $this->assertSame('Jane', $result['user']['name']);
        $this->assertStringContainsString('[REDACTED]', $result['user']['phone']);
    }

    // --- No patterns ---

    public function test_redact_returns_original_when_no_patterns(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.patterns', []);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('sensitive 123-45-6789');

        $this->assertSame('sensitive 123-45-6789', $result);
    }

    // --- Non-string/non-array passthrough ---

    public function test_redact_integer_returns_unchanged(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.patterns', ['all' => '/.*/']);

        $redactor = new DlpRedactor();
        $this->assertSame(42, $redactor->redact(42));
    }

    public function test_redact_null_returns_unchanged(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.patterns', ['all' => '/.*/']);

        $redactor = new DlpRedactor();
        $this->assertNull($redactor->redact(null));
    }

    // --- Multiple patterns ---

    public function test_multiple_patterns_applied(): void
    {
        $this->app['config']->set('vurb.dlp.enabled', true);
        $this->app['config']->set('vurb.dlp.strategy', 'remove');
        $this->app['config']->set('vurb.dlp.patterns', [
            'ssn' => '/\d{3}-\d{2}-\d{4}/',
            'phone' => '/\(\d{3}\)\s?\d{3}-\d{4}/',
        ]);

        $redactor = new DlpRedactor();
        $result = $redactor->redact('SSN: 123-45-6789, Phone: (555) 123-4567');

        $this->assertStringNotContainsString('123-45-6789', $result);
        $this->assertStringNotContainsString('(555) 123-4567', $result);
        // Two [REDACTED] for two matches
        $this->assertSame(2, substr_count($result, '[REDACTED]'));
    }
}
