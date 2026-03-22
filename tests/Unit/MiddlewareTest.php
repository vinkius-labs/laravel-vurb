<?php

namespace Vinkius\Vurb\Tests\Unit;

use Vinkius\Vurb\Http\Middleware\ValidateVurbToken;
use Vinkius\Vurb\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    public function test_valid_token_passes(): void
    {
        $token = config('vurb.internal_token');

        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => $token,
        ]);

        $response->assertOk();
    }

    public function test_missing_token_returns_403(): void
    {
        $response = $this->getJson('/_vurb/health');

        $response->assertStatus(403);
    }

    public function test_wrong_token_returns_403(): void
    {
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => 'completely_wrong_token',
        ]);

        $response->assertStatus(403);
    }

    public function test_empty_token_returns_403(): void
    {
        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => '',
        ]);

        $response->assertStatus(403);
    }

    public function test_missing_config_token_returns_500(): void
    {
        $this->app['config']->set('vurb.internal_token', '');

        $response = $this->getJson('/_vurb/health', [
            'X-Vurb-Token' => 'any_token',
        ]);

        $response->assertStatus(500);
    }
}
