<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        config()->set('signature.api_key', 'testing-api-key');

        $response = $this->postJson('/api/v1/signatures/initiate', []);

        $response->assertUnauthorized();
    }
}
