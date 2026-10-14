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

    public function test_books_index_accepts_blank_filters_for_initial_catalog_load(): void
    {
        $this->app->instance(LibraryRepository::class, \Mockery::mock(LibraryRepository::class, function ($library): void {
            $library->shouldReceive('books')->once()->with([
                'q' => null,
                'category_id' => null,
                'page' => 1,
                'size' => 20,
            ])->andReturn([
                'items' => [],
                'page' => 1,
                'size' => 20,
                'total' => 0,
            ]);
        }));

        $login = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'admin123']);
        $login->assertOk();

        $token = $login->json('access_token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/books?q=&category_id=&page=1&size=20')
            ->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonPath('size', 20)
            ->assertJsonPath('total', 0);
    }

    public function test_members_index_accepts_blank_q_filter_for_initial_listing(): void
    {
        $this->app->instance(LibraryRepository::class, \Mockery::mock(LibraryRepository::class, function ($library): void {
            $library->shouldReceive('members')->once()->with([
                'q' => null,
                'page' => 1,
                'size' => 20,
            ])->andReturn([
                'items' => [],
                'page' => 1,
                'size' => 20,
                'total' => 0,
            ]);
        }));

        $login = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'admin123']);
        $login->assertOk();

        $token = $login->json('access_token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/members?q=&page=1&size=20')
            ->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonPath('size', 20)
            ->assertJsonPath('total', 0);
    }

    public function test_books_store_accepts_valid_payload_for_new_book(): void
    {
        $this->app->instance(LibraryRepository::class, \Mockery::mock(LibraryRepository::class, function ($library): void {
            $library->shouldReceive('createBook')->once()->with([
                'title' => 'Nuevo libro',
                'author' => 'Autor demo',
                'isbn' => '9786073810073',
                'category_id' => '11111111-1111-4111-8111-111111111111',
                'total_copies' => 3,
            ])->andReturn((object) [
                'id' => '22222222-2222-4222-8222-222222222223',
                'title' => 'Nuevo libro',
                'author' => 'Autor demo',
                'isbn' => '9786073810073',
                'category_id' => '11111111-1111-4111-8111-111111111111',
                'total_copies' => 3,
                'available_copies' => 3,
                'cover_key' => null,
                'deleted_at' => null,
                'category_name' => 'Novela',
            ]);
        }));

        $login = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'admin123']);
        $login->assertOk();

        $token = $login->json('access_token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/books', [
                'title' => 'Nuevo libro',
                'author' => 'Autor demo',
                'isbn' => '9786073810073',
                'category_id' => '11111111-1111-4111-8111-111111111111',
                'total_copies' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'Nuevo libro')
            ->assertJsonPath('category.name', 'Novela');
    }
}
