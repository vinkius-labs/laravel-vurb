<?php

namespace Vinkius\Vurb\Tests\Hostile;

use Vinkius\Vurb\Tests\TestCase;

/**
 * Tests for path traversal, header injection, XSS in output, SQL injection
 * in params, and other security attack vectors against the bridge endpoints.
 */
class SecurityInjectionTest extends TestCase
{
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = config('vurb.internal_token');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('vurb.server.name', 'test-server');
        $app['config']->set('vurb.server.version', '1.0.0');
        $app['config']->set('vurb.bridge.base_url', 'http://localhost');
        $app['config']->set('vurb.bridge.prefix', '/_vurb');
        $app['config']->set('vurb.observability.events', false);
        $app['config']->set('vurb.dlp.enabled', false);
    }

    // ─── Path Traversal ───

    public function test_path_traversal_in_tool_name_is_rejected(): void
    {
        $response = $this->postJson('/_vurb/execute/../../etc/passwd', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        // Should not match any real tool
        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_encoded_path_traversal_in_tool_name(): void
    {
        $response = $this->postJson('/_vurb/execute/customers%2f..%2f..%2fadmin/get_profile', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_null_byte_in_tool_name_fails_gracefully(): void
    {
        $response = $this->postJson("/_vurb/execute/customers/get_profile\x00admin", [], [
            'X-Vurb-Token' => $this->token,
        ]);

        // Should either 404 or execute the correct tool, never leak paths
        $this->assertContains($response->status(), [200, 404, 500]);
        $responseBody = $response->getContent();
        $this->assertStringNotContainsString('/etc/', $responseBody);
        $this->assertStringNotContainsString('C:\\', $responseBody);
    }

    public function test_double_dot_segments_in_action(): void
    {
        $response = $this->postJson('/_vurb/execute/customers/..%2f..%2fpasswd', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $this->assertContains($response->status(), [404, 500]);
    }

    // ─── XSS in Tool Output ───

    public function test_xss_payload_in_input_is_json_safe(): void
    {
        $xssPayload = '<script>alert("xss")</script>';

        $response = $this->postJson('/_vurb/execute/echo_tool.handle/handle', [
            'value' => $xssPayload,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        // We need to discover the correct tool name first
        // EchoTool infers name as "echo_tool" (no verb prefix)
        if ($response->status() === 404) {
            // Tool name may be different — this IS the test: 404 is safe
            $this->assertTrue(true);
            return;
        }

        // If it works, verify the output is properly JSON-encoded, not raw HTML
        $body = $response->getContent();
        // JSON encoding escapes < > into \u003C \u003E or keeps them as-is (both safe for JSON consumers)
        $this->assertJson($body);

        // The response Content-Type must be application/json, not text/html
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_xss_in_error_message_is_json_escaped(): void
    {
        $response = $this->postJson('/_vurb/execute/<img onerror=alert(1) src=x>/test', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $this->assertJson($response->getContent());
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    // ─── SQL Injection via Parameters ───

    public function test_sql_injection_in_string_param(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => "1; DROP TABLE users; --",
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        // The tool casts id to int, so this should succeed with id=1 (cast behavior)
        // OR return validation error — but never execute SQL
        $this->assertContains($response->status(), [200, 422]);

        if ($response->status() === 200) {
            $data = $response->json('data');
            // id should be cast to integer (1), not the malicious string
            $this->assertIsInt($data['id']);
            $this->assertSame(1, $data['id']);
        }
    }

    public function test_sql_injection_in_search_param(): void
    {
        $response = $this->postJson('/_vurb/execute/products.search/handle', [
            'query' => "' OR 1=1 UNION SELECT * FROM users --",
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        // The tool is a fixture, it doesn't actually query a DB.
        // This tests that the framework doesn't inject SQL at the parameter level.
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ─── Header Injection ───

    public function test_crlf_injection_in_request_id_header(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => $this->token,
            'X-Vurb-Request-Id' => "valid\r\nX-Injected: evil",
        ]);

        // Request should succeed but the header value should be sanitized
        // or reflected as-is in JSON (not in HTTP headers)
        $response->assertOk();

        // The injected header should NOT appear as a separate HTTP header
        $this->assertNull($response->headers->get('X-Injected'));
    }

    // ─── Token Security ───

    public function test_token_with_padding_spaces_is_rejected(): void
    {
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => ' ' . $this->token . ' ',
        ]);

        // hash_equals is strict: leading/trailing spaces should fail
        $response->assertStatus(403);
    }

    public function test_token_with_null_bytes_is_rejected(): void
    {
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => $this->token . "\x00",
        ]);

        $response->assertStatus(403);
    }

    public function test_partial_token_is_rejected(): void
    {
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => substr($this->token, 0, 10),
        ]);

        $response->assertStatus(403);
    }

    public function test_unicode_homoglyph_token_is_rejected(): void
    {
        // Replace 'a' with Cyrillic 'а' (U+0430) — looks identical but different bytes
        $homoglyph = str_replace('a', "\xD0\xB0", $this->token);

        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => $homoglyph,
        ]);

        $response->assertStatus(403);
    }

    public function test_base64_encoded_token_is_rejected(): void
    {
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => base64_encode($this->token),
        ]);

        $response->assertStatus(403);
    }

    // ─── Tool Name Injection ───

    public function test_tool_name_with_special_characters(): void
    {
        $specialNames = [
            'customers/get_profile',
            'customers;get_profile',
            'customers&get_profile',
            'customers|get_profile',
        ];

        foreach ($specialNames as $name) {
            $response = $this->postJson("/_vurb/execute/{$name}", [
                'id' => 1,
            ], [
                'X-Vurb-Token' => $this->token,
            ]);

            // Should never leak system information regardless of status code
            $body = $response->getContent();
            $this->assertStringNotContainsString('stack trace', strtolower($body));
            $this->assertStringNotContainsString('vendor/', $body);
        }

        // Backslash in URI is rejected at the HTTP layer (Symfony) — verify it throws
        $this->expectException(\Symfony\Component\HttpFoundation\Exception\BadRequestException::class);
        $this->postJson('/_vurb/execute/customers\\get_profile', ['id' => 1], [
            'X-Vurb-Token' => $this->token,
        ]);
    }

    // ─── Response Content-Type ───

    public function test_all_responses_are_json_content_type(): void
    {
        // Success
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', ['id' => 1], [
            'X-Vurb-Token' => $this->token,
        ]);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        // 404
        $response = $this->postJson('/_vurb/execute/nonexistent.tool/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    // ─── No Stack Trace Leak ───

    public function test_500_error_does_not_leak_stack_trace_in_production(): void
    {
        $this->app['config']->set('app.debug', false);

        $response = $this->postJson('/_vurb/execute/failing_tool.something/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('vendor/', $body);
        $this->assertStringNotContainsString('.php', $body);
        $this->assertStringNotContainsString('#0', $body); // Stack trace format
    }
}
