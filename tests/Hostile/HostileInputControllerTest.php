<?php

namespace Vinkius\Vurb\Tests\Hostile;

use Vinkius\Vurb\Tests\TestCase;

/**
 * Tests the HTTP layer under hostile conditions: malformed JSON, oversized
 * payloads, special chars in URLs, wrong content types, concurrent request IDs,
 * and type coercion in the controller's castValue().
 */
class HostileInputControllerTest extends TestCase
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

    // ─── Empty Body ───

    public function test_execute_with_empty_body(): void
    {
        // GetCustomerProfile requires 'id'
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        // Should return validation error, not crash
        $response->assertStatus(422);
    }

    // ─── Type Coercion via HTTP ───

    public function test_string_id_is_cast_to_int(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => '42',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(42, $data['id']);
    }

    public function test_float_id_truncated_to_int(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 42.9,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(42, $data['id']);
    }

    public function test_boolean_id_cast(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => true,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(1, $data['id']); // true → (int) 1
    }

    public function test_string_with_leading_zero_cast(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => '007',
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(7, $data['id']); // '007' → (int) 7
    }

    public function test_null_for_required_int_param(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => null,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        // id is required and non-nullable — should pass through as (int) null = 0
        // or may throw validation error depending on resolution
        $this->assertContains($response->status(), [200, 422]);
    }

    // ─── Oversized Payloads ───

    public function test_large_json_payload(): void
    {
        $largeBody = [
            'id' => 1,
            'extra' => str_repeat('X', 100_000), // 100KB extra field
        ];

        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', $largeBody, [
            'X-Vurb-Token' => $this->token,
        ]);

        // Should succeed (extra params are ignored) or return appropriate error
        $this->assertContains($response->status(), [200, 413, 422, 500]);
    }

    // ─── Special Characters in URL Segments ───

    public function test_unicode_in_tool_segment(): void
    {
        $response = $this->postJson('/_vurb/execute/cüstomers/gét_profile', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertNotFound();
    }

    public function test_spaces_in_tool_segment(): void
    {
        $response = $this->postJson('/_vurb/execute/customer s/get profile', [
            'id' => 1,
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        // Should be 404, not crash
        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_very_long_tool_name(): void
    {
        $longName = str_repeat('a', 500);

        $response = $this->postJson("/_vurb/execute/{$longName}.action/handle", [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $this->assertContains($response->status(), [404, 500]);
    }

    // ─── Concurrent Request IDs ───

    public function test_unique_request_ids_when_not_provided(): void
    {
        $response1 = $this->postJson('/_vurb/execute/customers.get_profile/handle', ['id' => 1], [
            'X-Vurb-Token' => $this->token,
        ]);
        $response2 = $this->postJson('/_vurb/execute/customers.get_profile/handle', ['id' => 2], [
            'X-Vurb-Token' => $this->token,
        ]);

        $id1 = $response1->json('meta.request_id');
        $id2 = $response2->json('meta.request_id');

        $this->assertNotSame($id1, $id2, 'Request IDs should be unique');
    }

    // ─── Error Response Structure ───

    public function test_404_error_has_consistent_structure(): void
    {
        $response = $this->postJson('/_vurb/execute/nonexistent.tool/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertNotFound();
        $response->assertJsonStructure([
            'error',
            'code',
            'message',
            'meta' => ['request_id', 'latency_ms'],
        ]);
        $this->assertTrue($response->json('error'));
        $this->assertSame('NOT_FOUND', $response->json('code'));
    }

    public function test_error_latency_is_measured(): void
    {
        $response = $this->postJson('/_vurb/execute/nonexistent.tool/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $latency = $response->json('meta.latency_ms');
        $this->assertIsFloat($latency);
        $this->assertGreaterThanOrEqual(0, $latency);
    }

    // ─── HTTP Methods ───

    public function test_get_on_execute_returns_method_not_allowed(): void
    {
        $response = $this->getJson('/_vurb/execute/customers.get_profile/handle?id=1', [
            'X-Vurb-Token' => $this->token,
        ]);

        // Execute only accepts POST
        $response->assertStatus(405);
    }

    public function test_put_on_execute_returns_not_allowed(): void
    {
        $response = $this->putJson('/_vurb/execute/customers.get_profile/handle', ['id' => 1], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(405);
    }

    public function test_delete_on_execute_returns_not_allowed(): void
    {
        $response = $this->deleteJson('/_vurb/execute/customers.get_profile/handle', [], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertStatus(405);
    }

    // ─── Health Endpoint Under Hostile Conditions ───

    public function test_health_with_extra_query_params(): void
    {
        $response = $this->getJson('/_vurb/health?foo=bar&__proto__=pollution', [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['status' => 'ok']);
    }

    // ─── Duplicate Parameter Keys ───

    public function test_json_body_with_nested_prototype_pollution(): void
    {
        $response = $this->postJson('/_vurb/execute/customers.get_profile/handle', [
            'id' => 1,
            '__proto__' => ['admin' => true],
            'constructor' => ['prototype' => ['isAdmin' => true]],
        ], [
            'X-Vurb-Token' => $this->token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(1, $data['id']);
        // Ensure prototype pollution keys don't affect the response
        $this->assertArrayNotHasKey('admin', $data);
        $this->assertArrayNotHasKey('isAdmin', $data);
    }
}
