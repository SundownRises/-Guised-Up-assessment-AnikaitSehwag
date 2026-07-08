<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Post;
use App\Models\Interaction;
use App\Models\Relationship;
use App\Services\EmbeddingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Seed the application's database with test posts, interactions, and relationships.
     */
    public function run(): void
    {
        $embeddingService = app(EmbeddingService::class);

        $ravi = User::where('email', 'ravi@guisedup.com')->first();
        $anika = User::where('email', 'anika@guisedup.com')->first();

        $now = Carbon::now();

        // Ravi's posts (high authenticity) — 7 posts
        $raviPosts = [
            [
                'text' => 'Just finished a 3-hour hike through the coastal trail near my apartment. The morning fog made everything look surreal, and I spotted two deer near the creek. Nature has a way of resetting your mind.',
                'image_url' => 'https://images.unsplash.com/photo-1551632811-561732d1e306?w=600',
                'authenticity_score' => 0.92,
                'created_at' => $now->copy()->subHours(6),
            ],
            [
                'text' => 'Been thinking about how we measure success. My grandfather built furniture for 40 years and never once called himself an entrepreneur. He just made beautiful things for people who needed them.',
                'image_url' => null,
                'authenticity_score' => 0.95,
                'created_at' => $now->copy()->subHours(18),
            ],
            [
                'text' => 'Tried making sourdough for the first time. The starter took 5 days and the first loaf was dense as a brick. But the smell of fresh bread at 6am made it all worth it.',
                'image_url' => 'https://images.unsplash.com/photo-1585478259715-876acc5be8eb?w=600',
                'authenticity_score' => 0.88,
                'created_at' => $now->copy()->subDays(1)->subHours(12),
            ],
            [
                'text' => "Reading 'Sapiens' for the second time and getting completely different things from it. Funny how a book changes when you change.",
                'image_url' => null,
                'authenticity_score' => 0.94,
                'created_at' => $now->copy()->subDays(2)->subHours(4),
            ],
            [
                'text' => 'The community garden on 5th street is finally producing tomatoes. Three months of weekend mornings, but splitting the harvest with neighbors feels different from buying at the store.',
                'image_url' => 'https://images.unsplash.com/photo-1592150621744-aca64f48394a?w=600',
                'authenticity_score' => 0.90,
                'created_at' => $now->copy()->subDays(3),
            ],
            [
                'text' => 'Spent the afternoon teaching my niece to ride a bike. She fell seven times and got back up eight. Kids are more resilient than we give them credit for.',
                'image_url' => null,
                'authenticity_score' => 0.93,
                'created_at' => $now->copy()->subDays(3)->subHours(18),
            ],
            [
                'text' => 'Discovered a tiny bookshop tucked behind the main street today. The owner has been there 30 years and knows every book by heart. Some places resist time.',
                'image_url' => null,
                'authenticity_score' => 0.91,
                'created_at' => $now->copy()->subDays(4)->subHours(8),
            ],
        ];

        // Anika's posts (mixed authenticity) — 5 posts
        $anikaPosts = [
            [
                'text' => 'Working on a new project that combines machine learning with social connections. Early days but the results are promising. Sometimes the best features come from understanding people, not just data.',
                'image_url' => null,
                'authenticity_score' => 0.89,
                'created_at' => $now->copy()->subHours(3),
            ],
            [
                'text' => 'CHECK OUT MY NEW PROFILE!!! #blessed #newme #transformation #goals #livingmybestlife #nofilter',
                'image_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=600',
                'authenticity_score' => 0.35,
                'created_at' => $now->copy()->subDays(1),
            ],
            [
                'text' => "Had coffee with an old friend today. We hadn't talked in two years but picked up right where we left off. Real connections don't expire.",
                'image_url' => null,
                'authenticity_score' => 0.93,
                'created_at' => $now->copy()->subDays(2),
            ],
            [
                'text' => 'Sunday morning routine: journal, coffee, a long walk with no destination. The best ideas come when you stop looking for them.',
                'image_url' => null,
                'authenticity_score' => 0.91,
                'created_at' => $now->copy()->subDays(3)->subHours(6),
            ],
            [
                'text' => 'AMAZING DEAL!!! Visit http://spam.example.com and http://more-spam.example.com and http://even-more.example.com for FREE stuff!!! #ad #sponsored #deal #wow #incredible',
                'image_url' => null,
                'authenticity_score' => 0.15,
                'created_at' => $now->copy()->subDays(4)->subHours(16),
            ],
        ];

        // Create Ravi's posts with real embeddings from embedding service
        $createdRaviPosts = [];
        foreach ($raviPosts as $index => $postData) {
            $embedding = $embeddingService->generateEmbedding($postData['text'])
                ?? $this->generateFallbackEmbedding($index);

            $createdRaviPosts[] = Post::create([
                'user_id' => $ravi->id,
                'text' => $postData['text'],
                'image_url' => $postData['image_url'],
                'authenticity_score' => $postData['authenticity_score'],
                'embedding' => $embedding,
                'created_at' => $postData['created_at'],
                'updated_at' => $postData['created_at'],
            ]);
        }

        // Create Anika's posts with real embeddings
        $createdAnikaPosts = [];
        foreach ($anikaPosts as $index => $postData) {
            $embedding = $embeddingService->generateEmbedding($postData['text'])
                ?? $this->generateFallbackEmbedding($index + 7);

            $createdAnikaPosts[] = Post::create([
                'user_id' => $anika->id,
                'text' => $postData['text'],
                'image_url' => $postData['image_url'],
                'authenticity_score' => $postData['authenticity_score'],
                'embedding' => $embedding,
                'created_at' => $postData['created_at'],
                'updated_at' => $postData['created_at'],
            ]);
        }

        // Create pre-logged interactions (Anika interacting with Ravi's posts)
        // Anika viewed Ravi's posts 1, 2, 3, 4, 5
        $viewedPostIndices = [0, 1, 2, 3, 4];
        foreach ($viewedPostIndices as $postIndex) {
            Interaction::create([
                'user_id' => $anika->id,
                'post_id' => $createdRaviPosts[$postIndex]->id,
                'type' => 'view',
            ]);
        }

        // Anika reacted to Ravi's posts 1, 2, 5
        $reactedPostIndices = [0, 1, 4];
        foreach ($reactedPostIndices as $postIndex) {
            Interaction::create([
                'user_id' => $anika->id,
                'post_id' => $createdRaviPosts[$postIndex]->id,
                'type' => 'reaction',
            ]);
        }

        // Anika replied to Ravi's post 2
        Interaction::create([
            'user_id' => $anika->id,
            'post_id' => $createdRaviPosts[1]->id,
            'type' => 'reply',
        ]);

        // Create pre-computed relationship score
        // Anika -> Ravi: 5 views * 1 + 3 reactions * 2 + 1 reply * 3 = 5 + 6 + 3 = 14
        Relationship::create([
            'user_id' => $anika->id,
            'target_user_id' => $ravi->id,
            'score' => 14,
        ]);
    }

    /**
     * Generate a deterministic 384-dim embedding vector for a given post index.
     * Formula: cos((i + j*10) * 0.1) * 0.3 + sin(i * 0.05) * 0.2, then normalize.
     */
    private function generateFallbackEmbedding(int $postIndex): array
    {
        $rawVector = [];
        for ($i = 0; $i < 384; $i++) {
            $rawVector[] = cos(($i + $postIndex * 10) * 0.1) * 0.3 + sin($i * 0.05) * 0.2;
        }

        // Normalize to unit length
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $rawVector)));
        $normalized = array_map(fn($v) => $v / $magnitude, $rawVector);

        return $normalized;
    }
}
