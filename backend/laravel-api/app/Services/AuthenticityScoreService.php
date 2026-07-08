<?php

namespace App\Services;

class AuthenticityScoreService
{
    /**
     * Calculate the authenticity score for a post.
     *
     * Combines 7 sub-signals with weighted contributions to produce
     * a final score clamped to [0, 1].
     */
    public function calculate(string $text, ?string $imageUrl): float
    {
        $score = (0.25 * $this->imageFilterDetection($imageUrl))
            + (0.20 * $this->imageRetouchingDetection($imageUrl))
            + (0.15 * $this->textLengthScore($text))
            + (0.15 * $this->hashtagDensityScore($text))
            + (0.10 * $this->excessiveCapsScore($text))
            + (0.10 * $this->urlSpamScore($text))
            + (0.05 * $this->hasOriginalTextScore($text));

        return max(0.0, min(1.0, $score));
    }

    /**
     * Image filter detection (placeholder).
     * Returns 1.0 if no image, 0.8 if image present.
     */
    private function imageFilterDetection(?string $imageUrl): float
    {
        return $imageUrl === null ? 1.0 : 0.8;
    }

    /**
     * Image retouching detection (placeholder).
     * Returns 1.0 if no image, 0.8 if image present.
     */
    private function imageRetouchingDetection(?string $imageUrl): float
    {
        return $imageUrl === null ? 1.0 : 0.8;
    }

    /**
     * Text length score. Optimal range is 50-500 characters.
     * Linearly scales down outside that range.
     */
    private function textLengthScore(string $text): float
    {
        $len = strlen($text);

        if ($len >= 50 && $len <= 500) {
            return 1.0;
        }

        if ($len < 50) {
            return $len / 50;
        }

        // Above 500: linearly decrease
        return max(0.0, 1 - ($len - 500) / 500);
    }

    /**
     * Hashtag density score. <=2 hashtags is normal, >5 penalizes fully.
     */
    private function hashtagDensityScore(string $text): float
    {
        $count = substr_count($text, '#');

        if ($count <= 2) {
            return 1.0;
        }

        if ($count >= 5) {
            return 0.0;
        }

        return (5 - $count) / 3;
    }

    /**
     * Excessive caps score. >30% uppercase letters indicates clickbait.
     */
    private function excessiveCapsScore(string $text): float
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $text);
        $totalLetters = strlen($letters);

        if ($totalLetters === 0) {
            return 1.0;
        }

        $uppercaseCount = strlen(preg_replace('/[^A-Z]/', '', $letters));
        $uppercasePercent = $uppercaseCount / $totalLetters;

        if ($uppercasePercent <= 0.30) {
            return 1.0;
        }

        // Linearly decrease from 1.0 at 30% to 0.0 at 100%
        return max(0.0, 1 - ($uppercasePercent - 0.30) / 0.70);
    }

    /**
     * URL spam score. >2 URLs penalizes the post.
     */
    private function urlSpamScore(string $text): float
    {
        $count = preg_match_all('/https?:\/\//', $text);

        if ($count <= 2) {
            return 1.0;
        }

        return max(0.0, 1 - ($count - 2) / 3);
    }

    /**
     * Has original text score. Image-only posts are penalized.
     */
    private function hasOriginalTextScore(string $text): float
    {
        return strlen($text) > 0 ? 1.0 : 0.0;
    }
}
