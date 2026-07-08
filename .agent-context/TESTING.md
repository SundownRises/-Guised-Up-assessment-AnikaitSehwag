# Testing Strategy

## Overview

Minimum requirement: 3 feature tests for critical logic. We target 9 test cases across 3 test files.

## Backend Tests (Laravel PHPUnit)

### Feature Tests

| Test File | What It Covers |
|-----------|---------------|
| `PostCreationTest.php` | POST /api/posts — validates input, stores post, generates embedding |
| `FeedTest.php` | GET /api/feed — returns ranked, paginated results for auth'd user |
| `SearchTest.php` | GET /api/search — returns semantically relevant results |

### Test Cases

#### PostCreationTest
1. **test_authenticated_user_can_create_post** — happy path, returns 201 with post data and `embedding_status: generated`
2. **test_unauthenticated_user_cannot_create_post** — returns 401
3. **test_post_creation_requires_text** — validation error 422 when text is missing

#### FeedTest
1. **test_feed_returns_paginated_results** — returns 20 items per page with meta (page, per_page, total)
2. **test_feed_requires_authentication** — returns 401 without token
3. **test_feed_ranks_by_relationship_depth** — posts from frequently-interacted users appear first (relationship_depth has highest weight 0.35)

#### SearchTest
1. **test_search_returns_results_for_valid_query** — returns up to 10 results with similarity_score
2. **test_search_returns_empty_for_no_match** — graceful empty response (empty data array)
3. **test_search_requires_query_parameter** — returns 422 when q is missing

### Running Tests

```bash
cd backend/laravel-api
php artisan test
# or
./vendor/bin/phpunit --filter=PostCreationTest
```

### Test Environment

- **Database**: Must use PostgreSQL test database (pgvector requires it — SQLite won't work)
- `RefreshDatabase` trait on all feature tests
- Seed test users in test setUp or via factories
- **Mock the Python embedding service** in ALL tests — return a fixed 384-dim vector
- The mock should be bound in the service container: `$this->mock(EmbeddingService::class)`

### How to Mock the Embedding Service

```php
// In test setUp or individual test
$this->mock(EmbeddingService::class, function ($mock) {
    $mock->shouldReceive('generateEmbedding')
         ->andReturn(array_fill(0, 384, 0.1)); // Fixed 384-dim vector
});
```

### What Tests Verify About Ranking

The `FeedTest::test_feed_ranks_by_relationship_depth` test should:
1. Create 2 users (viewer + author)
2. Create posts from the author
3. Create a relationship record with a high score between viewer → author
4. Create posts from a third user (no relationship)
5. Assert that the author's posts appear before the third user's posts in the feed

## Python Embedding Service Tests (Optional)

If time permits:
- Test that `/embed` endpoint returns correct vector dimensions (384)
- Test that the mock fallback activates when model is unavailable
- Test input validation (empty text returns error)

```bash
cd backend/python-embedding
pytest test_main.py
```

## React Native Tests (Out of Scope)

Given the 8-hour time constraint, mobile tests are deprioritized. Manual testing covers:
- All states (loading, data, empty, error)
- Infinite scroll loads next page
- Search bar switches between feed and search results
- Heart button toggles (filled/unfilled)
- Pull-to-refresh works

## SQL Query Validation

Queries in `/sql/queries.sql` will be validated by:
1. Running against a seeded PostgreSQL database
2. Checking they return correct results with known test data
3. Verifying no syntax errors
4. All use PostgreSQL syntax (not ANSI-generic)

## Test Data Strategy

### Seeders provide (for running the app):
- 2 users: Anika (anika@guisedup.com) + Ravi (ravi@guisedup.com)
- Sanctum tokens output to console
- 10-15 sample posts with varied authenticity scores (some genuine, some with hashtag spam)
- Pre-logged interactions between Anika and Ravi's posts (views, replies, reactions)
- Pre-computed relationship score (Anika → Ravi)
- Pre-computed interest_vector on Anika
- Mocked deterministic embeddings (384-dim vectors) on all seeded posts

### Factories provide (for tests):
- `UserFactory` — generates random users
- `PostFactory` — generates posts with random text and mock 384-dim embeddings
- `InteractionFactory` — generates interactions of various types

### Key: Seeders vs Factories
- **Seeders**: Run once to set up demo data. Make the app demonstrate ranking behavior out of the box.
- **Factories**: Used in tests only. Create isolated data for each test case with RefreshDatabase.

## What We're NOT Testing

- UI pixel-perfection (manual review)
- Performance/load testing (out of scope for take-home)
- End-to-end mobile-to-backend flow (would need full stack running)
- Python model accuracy (trusting sentence-transformers)
- Image authenticity detection (placeholder logic — trivial to test)
- Reaction toggle behavior (can be added as 4th test if time permits)
