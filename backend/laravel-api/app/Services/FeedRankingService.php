<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Support\Collection;

class FeedRankingService
{
    /**
     * Get a ranked feed for the given user.
     *
     * Scores posts using authenticity, relationship depth, semantic similarity,
     * and time decay, then returns a paginated result.
     */
    public function getFeed(User $user, int $page = 1, int $perPage = 20): array
    {
        // a. Fetch candidate pool: 500 most recent posts
        $posts = Post::orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        // b. Eager load relationships
        $posts->load('user');

        // c. Get viewer's relationship scores (raw scores keyed by target_user_id)
        $relationshipScores = Relationship::where('user_id', $user->id)
            ->pluck('score', 'target_user_id');

        // d. Get user's interest vector
        $interestVector = $user->interest_vector;

        // e. Score each post
        $scoredPosts = $posts->map(function ($post) use ($relationshipScores, $interestVector) {
            // f. Authenticity: already [0, 1]
            $authenticity = (float) ($post->authenticity_score ?? 0);

            // g. Relationship depth: normalize raw score
            $rawScore = $relationshipScores[$post->user_id] ?? 0;
            $relationshipDepth = min($rawScore, 100) / 100;

            // h. Semantic similarity
            $semanticSimilarity = $this->calculateSemanticSimilarity($interestVector, $post->embedding);

            // i. Time decay
            $ageInHours = now()->diffInMinutes($post->created_at) / 60;
            $timeDecay = 1 - exp(-0.02 * $ageInHours);

            // e. Calculate final score
            $score = (0.25 * $authenticity)
                + (0.35 * $relationshipDepth)
                + (0.25 * $semanticSimilarity)
                - (0.15 * $timeDecay);

            $post->feed_score = $score;

            return $post;
        });

        // j. Sort by score descending
        $sorted = $scoredPosts->sortByDesc('feed_score')->values();

        // k. Paginate
        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;
        $pageSlice = $sorted->slice($offset, $perPage)->values();

        // l. Return result
        return [
            'posts' => $pageSlice,
            'total' => $total,
        ];
    }

    /**
     * Calculate semantic similarity between user interest vector and post embedding.
     */
    private function calculateSemanticSimilarity(mixed $interestVector, mixed $embedding): float
    {
        if ($interestVector === null) {
            return 0.5;
        }

        if ($embedding === null) {
            return 0.0;
        }

        $interestVector = $this->toArray($interestVector);
        $embeddingArray = $this->toArray($embedding);

        if (!is_array($embeddingArray) || empty($embeddingArray)) {
            return 0.0;
        }

        return $this->cosineSimilarity($interestVector, $embeddingArray);
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * Returns dot_product(a, b) / (magnitude(a) * magnitude(b))
     */
    private function toArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof \Pgvector\Laravel\Vector) {
            return $value->toArray();
        }
        if (is_string($value)) {
            return json_decode($value, true);
        }
        if (method_exists($value, 'toArray')) {
            return $value->toArray();
        }
        return null;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        if ($length === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
