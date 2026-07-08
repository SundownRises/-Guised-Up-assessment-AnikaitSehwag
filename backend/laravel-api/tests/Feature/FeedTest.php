<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Relationship;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
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

    public function test_feed_returns_paginated_results(): void
    {
        Post::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/feed?page=1&per_page=20');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'user', 'text', 'authenticity_score', 'created_at', 'time_ago']],
                'meta' => ['page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_feed_requires_authentication(): void
    {
        $response = $this->getJson('/api/feed');

        $response->assertStatus(401);
    }

    public function test_feed_ranks_by_relationship_depth(): void
    {
        $closeAuthor = User::factory()->create();
        $strangerAuthor = User::factory()->create();

        // Create relationship: viewer has high relationship with closeAuthor
        Relationship::create([
            'user_id' => $this->user->id,
            'target_user_id' => $closeAuthor->id,
            'score' => 80,
        ]);

        // Create posts at the same time to neutralize time decay
        $strangerPost = Post::factory()->create([
            'user_id' => $strangerAuthor->id,
            'authenticity_score' => 0.9,
            'created_at' => now(),
        ]);

        $closePost = Post::factory()->create([
            'user_id' => $closeAuthor->id,
            'authenticity_score' => 0.9,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/feed');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // closeAuthor's post should be first due to higher relationship depth
        $this->assertEquals($closePost->id, $data[0]['id']);
        $this->assertEquals($strangerPost->id, $data[1]['id']);
    }
}
