<?php

namespace Tests\Feature;

use Library\Application\Ports\LibraryRepository;
use Tests\TestCase;

final class HealthAndAuthenticationTest extends TestCase
{
    public function test_health_endpoint_is_public_and_uses_the_contract_body(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_api_requires_a_bearer_token_for_protected_routes(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertUnauthorized()
            ->assertExactJson([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Se requiere autenticación válida.',
                'details' => [],
            ]);
    }

    public function test_login_rejects_unknown_users_without_disclosing_which_credential_failed(): void
    {
        $library = \Mockery::mock(LibraryRepository::class);
        $library->shouldReceive('userByUsername')->once()->with('nobody')->andReturn(null);
        $this->app->instance(LibraryRepository::class, $library);

        $this->postJson('/api/v1/auth/login', ['username' => 'nobody', 'password' => 'not-a-real-secret'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_CREDENTIALS')
            ->assertJsonMissingPath('password_hash');
    }
}
