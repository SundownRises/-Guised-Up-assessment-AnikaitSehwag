<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('generateEmbedding')
                 ->andReturn(array_fill(0, 384, 0.1));
        });
    }

    public function test_authenticated_user_can_create_post(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/posts', [
                'text' => 'This is a genuine post about my morning walk through the park.',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'user', 'text', 'authenticity_score', 'created_at', 'time_ago'],
                'meta' => ['embedding_status'],
            ])
            ->assertJsonPath('meta.embedding_status', 'generated');
    }

    public function test_unauthenticated_user_cannot_create_post(): void
    {
        $response = $this->postJson('/api/posts', [
            'text' => 'This should fail.',
        ]);

        $response->assertStatus(401);
    }

    public function test_post_creation_requires_text(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/posts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }
}
