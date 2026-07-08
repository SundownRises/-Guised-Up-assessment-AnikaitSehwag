# Technical Solution Document — Real Connections Feed

**Project**: Guised Up — Real Connections Feed  
**Author**: Anika  
**Date**: July 2026  
**Stack**: React Native (Expo) + Laravel 11 + Python FastAPI + PostgreSQL (pgvector)

---

## Table of Contents

1. [System Architecture](#1-system-architecture)
2. [Database Schema Design](#2-database-schema-design)
3. [Vector Embedding Strategy](#3-vector-embedding-strategy)
4. [API Design](#4-api-design)
5. [Feed Ranking Algorithm](#5-feed-ranking-algorithm)
6. [AI Agentic Tools Used](#6-ai-agentic-tools-used)
7. [Trade-offs & Assumptions](#7-trade-offs--assumptions)

---

## 1. System Architecture

### High-Level Overview

The system is a three-tier architecture: a React Native mobile client communicates with a Laravel PHP API, which delegates embedding generation to a stateless Python microservice. All data (relational and vector) lives in a single PostgreSQL database with the pgvector extension.

### Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│                         MOBILE CLIENT                                 │
│                                                                       │
│   ┌─────────────────────────────────────────────────────────────┐    │
│   │              React Native (Expo)                             │    │
│   │                                                             │    │
│   │  Feed Screen ─── Search Bar ─── Post Cards ─── Reactions    │    │
│   └─────────────────────────────┬───────────────────────────────┘    │
└─────────────────────────────────┼────────────────────────────────────┘
                                  │
                                  │ HTTPS
                                  │ Authorization: Bearer {token}
                                  ▼
┌──────────────────────────────────────────────────────────────────────┐
│                         LARAVEL API LAYER                             │
│                                                                       │
│   ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌──────────────┐  │
│   │    POST    │  │    GET     │  │    GET     │  │    POST      │  │
│   │ /api/posts │  │ /api/feed  │  │/api/search │  │/api/interact │  │
│   └─────┬──────┘  └─────┬──────┘  └─────┬──────┘  └──────┬───────┘  │
│         │                │               │                 │          │
│         ▼                ▼               ▼                 ▼          │
│   ┌──────────────────────────────────────────────────────────────┐   │
│   │                     SERVICE LAYER                             │   │
│   │                                                              │   │
│   │  EmbeddingService    FeedRankingService   AuthenticityScore  │   │
│   └──────────┬───────────────────┬───────────────────────────────┘   │
│              │                   │                                     │
│   ┌──────────┘                   └──────────────┐                    │
│   │  Laravel Sanctum (Auth Middleware)          │                    │
└───┼─────────────────────────────────────────────┼────────────────────┘
    │                                             │
    │ HTTP (internal, port 8001)                  │ Eloquent ORM
    ▼                                             ▼
┌────────────────────────┐          ┌──────────────────────────────────┐
│  PYTHON EMBEDDING SVC  │          │         POSTGRESQL 16            │
│                        │          │         + pgvector 0.7+          │
│  FastAPI + Uvicorn     │          │                                  │
│                        │          │  ┌────────┐ ┌──────────────┐     │
│  Model:                │          │  │ users  │ │    posts     │     │
│  all-MiniLM-L6-v2     │          │  │        │ │  + embedding │     │
│  (384-dim output)      │          │  └────────┘ │  (vector)    │     │
│                        │          │             └──────────────┘     │
│  POST /embed           │          │  ┌──────────────┐ ┌──────────┐  │
│  → accepts text        │          │  │ interactions │ │relations │  │
│  → returns float[384]  │          │  └──────────────┘ └──────────┘  │
└────────────────────────┘          └──────────────────────────────────┘
```

### Request Flows

**Creating a Post:**
1. Mobile sends `POST /api/posts` with `{ text, image_url? }` + Bearer token
2. Laravel validates input via `CreatePostRequest`
3. `AuthenticityScoreService` computes score from text heuristics + image placeholders
4. `EmbeddingService` calls Python service `POST http://localhost:8001/embed` with the text
5. Python returns 384-dimensional vector
6. Post is stored in PostgreSQL with embedding (via pgvector) and authenticity score
7. Returns 201 with post data

**Fetching the Feed:**
1. Mobile sends `GET /api/feed?page=1` + Bearer token
2. `FeedRankingService` fetches all candidate posts (no pre-filtering — all posts are candidates)
3. For each candidate, computes: `score = 0.25*authenticity + 0.35*relationship_depth + 0.25*semantic_similarity - 0.15*time_decay`
4. Sorts by score descending, paginates (20 per page)
5. Returns paginated results with meta

**Semantic Search:**
1. Mobile sends `GET /api/search?q=funny travel stories` + Bearer token
2. `EmbeddingService` sends query text to Python service, gets 384-dim vector
3. Laravel queries pgvector using cosine distance operator (`<=>`)
4. Returns top 10 results where similarity >= 0.2

**Logging an Interaction:**
1. Mobile sends `POST /api/interactions` with `{ post_id, type }` + Bearer token
2. Interaction is stored in `interactions` table
3. `relationships` table is updated: increment viewer → post_author score by weight (view=1, reaction=2, reply=3)
4. User's `interest_vector` is updated via exponential moving average (0.9 * old + 0.1 * post_embedding)

---

## 2. Database Schema Design

### Entity Relationship Diagram

```
┌───────────────┐       ┌───────────────────┐       ┌──────────────────┐
│    users      │       │      posts        │       │  interactions    │
├───────────────┤       ├───────────────────┤       ├──────────────────┤
│ id (PK)       │◄──┐   │ id (PK)           │◄──┐   │ id (PK)          │
│ username      │   │   │ user_id (FK)──────┼───┘   │ user_id (FK)─────┼──►users
│ email         │   │   │ text              │       │ post_id (FK)─────┼──►posts
│ avatar_url    │   └───┼─────────(FK)      │       │ type             │
│ interest_vec  │       │ image_url         │       │ created_at       │
│ created_at    │       │ authenticity_score │       └──────────────────┘
│ updated_at    │       │ embedding (vec384)│
└───────┬───────┘       │ created_at        │
        │               │ updated_at        │
        │               └───────────────────┘
        │
        │       ┌───────────────────────┐
        └──────►│    relationships      │
                ├───────────────────────┤
                │ id (PK)               │
                │ user_id (FK)──────────┼──►users (the viewer)
                │ target_user_id (FK)───┼──►users (the post author)
                │ score                 │
                │ updated_at            │
                └───────────────────────┘
```

### Table Definitions

#### `users`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | BIGINT | PK, auto-increment | |
| username | VARCHAR(50) | UNIQUE, NOT NULL | |
| email | VARCHAR(255) | UNIQUE, NOT NULL | |
| password | VARCHAR(255) | NOT NULL | Hashed via bcrypt |
| avatar_url | VARCHAR(500) | NULLABLE | URL to profile image |
| interest_vector | vector(384) | NULLABLE | Rolling avg of interacted post embeddings |
| created_at | TIMESTAMP | NOT NULL | |
| updated_at | TIMESTAMP | NOT NULL | |

#### `posts`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | BIGINT | PK, auto-increment | |
| user_id | BIGINT | FK → users.id, NOT NULL | Post author |
| text | TEXT | NOT NULL | Post content (50–5000 chars) |
| image_url | VARCHAR(500) | NULLABLE | Optional image link |
| authenticity_score | DECIMAL(3,2) | NOT NULL, DEFAULT 0.50 | Range [0.00, 1.00] |
| embedding | vector(384) | NULLABLE | NULL if embedding service was down |
| created_at | TIMESTAMP | NOT NULL | |
| updated_at | TIMESTAMP | NOT NULL | |

#### `interactions`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | BIGINT | PK, auto-increment | |
| user_id | BIGINT | FK → users.id, NOT NULL | The user who performed the action |
| post_id | BIGINT | FK → posts.id, NOT NULL | Target post |
| type | VARCHAR(20) | NOT NULL | One of: 'view', 'reply', 'reaction' |
| created_at | TIMESTAMP | NOT NULL | |

#### `relationships` (materialized relationship depth)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | BIGINT | PK, auto-increment | |
| user_id | BIGINT | FK → users.id, NOT NULL | The viewer |
| target_user_id | BIGINT | FK → users.id, NOT NULL | The content creator |
| score | DECIMAL(8,2) | NOT NULL, DEFAULT 0.00 | Accumulated weighted interactions |
| updated_at | TIMESTAMP | NOT NULL | |

**Unique constraint**: `(user_id, target_user_id)` — one relationship row per directional pair.

### Indexes

| Table | Index | Type | Purpose |
|-------|-------|------|---------|
| posts | `(user_id, created_at)` | B-tree | Feed queries filtering by author |
| posts | `(embedding)` | IVFFlat (pgvector) | Vector similarity search |
| posts | `(created_at)` | B-tree | Time-based ordering |
| interactions | `(user_id, type, created_at)` | B-tree composite | Activity aggregation (SQL D1) |
| interactions | `(post_id, type)` | B-tree composite | View/reaction counts (SQL D3) |
| interactions | `(user_id, post_id, type)` | B-tree composite | Deduplication check for reactions |
| relationships | `(user_id, target_user_id)` | B-tree UNIQUE | Fast lookup of relationship score |

### Design Decisions

- **Single `interactions` table** for all types (view/reply/reaction) rather than separate tables. Simpler schema, one index handles aggregation queries, and the SQL queries in Part D work against a single table.
- **Materialized `relationships` table** avoids expensive COUNT/SUM on every feed request. Updated incrementally on each interaction.
- **`interest_vector` on users** rather than a separate table — it's always fetched with the user, one-to-one relationship, avoids an extra JOIN on feed generation.
- **No soft deletes** — not required by the brief, adds complexity. Hard deletes with foreign key cascades.

---

## 3. Vector Embedding Strategy

### Choice: pgvector (PostgreSQL Extension)

**Selected**: pgvector 0.7+ as a PostgreSQL extension  
**Alternatives considered**: Pinecone, Qdrant, Weaviate, Chroma

| Criteria | pgvector | Pinecone | Qdrant |
|----------|----------|----------|--------|
| Infrastructure | Same DB — zero extra ops | Managed SaaS | Self-hosted or cloud |
| Latency | No network hop for vector queries | Network roundtrip per query | Network roundtrip |
| Cost | Free (PG extension) | Paid per vector stored | Free self-hosted |
| Scale limit | ~1M vectors performant | Billions | Billions |
| Setup complexity | One `CREATE EXTENSION` | API key + SDK + separate infra | Docker + config |
| Suitable for MVP | Yes | Overkill | Overkill |

**Why pgvector wins for this project:**
1. We're already using PostgreSQL — no additional infrastructure, deployments, or network configuration
2. MVP scale is <100K posts — pgvector handles this comfortably with IVFFlat indexing
3. Queries can JOIN vector similarity with relational data (user relationships, timestamps) in a single SQL query
4. Simpler for the evaluator to run — `CREATE EXTENSION vector` and done

**Migration path**: If Guised Up scales past ~500K posts and search latency degrades, migrate vector data to Qdrant (open-source, self-hostable) while keeping relational data in PostgreSQL.

### Embedding Model: all-MiniLM-L6-v2

| Property | Value |
|----------|-------|
| Model | `sentence-transformers/all-MiniLM-L6-v2` |
| Output dimensions | 384 |
| Model size | ~80MB |
| Inference time | ~10-50ms per sentence (CPU) |
| Cost | Free, runs locally |
| Quality | Strong for semantic similarity tasks; top performer on STSB benchmark for its size class |

**Why this model:**
- Free with no API key — evaluator can run it immediately
- 384 dimensions is compact (lower storage, faster similarity computation vs. 768 or 1536-dim models)
- Runs on CPU — no GPU required for inference
- Well-suited for short social media text (trained on sentence pairs)

**Fallback**: If the model can't be loaded (low memory, CI environment), the embedding service returns a deterministic mock vector based on a hash of the input text. This ensures the system is always runnable and testable.

### How Embeddings Are Used

1. **Post creation**: Text is embedded and stored in `posts.embedding`
2. **Search**: Query text is embedded, then compared via cosine distance (`<=>` operator) against all post embeddings
3. **User interest**: On interaction, the post's embedding is blended into `users.interest_vector` using exponential moving average
4. **Feed ranking**: Cosine similarity between `users.interest_vector` and each candidate `posts.embedding` produces the semantic_similarity signal

### pgvector Operations

```sql
-- Enable extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Add vector column
ALTER TABLE posts ADD COLUMN embedding vector(384);

-- Create IVFFlat index (after data is loaded)
CREATE INDEX posts_embedding_idx ON posts
  USING ivfflat (embedding vector_cosine_ops)
  WITH (lists = 100);

-- Cosine similarity search
SELECT id, text, 1 - (embedding <=> $1) AS similarity
FROM posts
WHERE embedding IS NOT NULL
ORDER BY embedding <=> $1
LIMIT 10;
```

---

## 4. API Design

### Authentication

All endpoints require authentication via **Laravel Sanctum** (token-based).

- Token is obtained via seeder output (for demo) or a login endpoint (future)
- Sent as: `Authorization: Bearer {token}`
- Middleware: `auth:sanctum` on all `/api/*` routes
- Unauthenticated requests receive `401 Unauthorized`

### Response Format Convention

All successful responses follow this structure:

```json
{
  "data": { ... },
  "meta": { ... }
}
```

Error responses:
```json
{
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Specific validation error"]
  }
}
```

---

### Endpoint 1: Create Post

**`POST /api/posts`**

Creates a new post, generates embedding, computes authenticity score.

**Request:**
```json
{
  "text": "Just had an amazing conversation with a stranger at the coffee shop. No phones, just real talk.",
  "image_url": "https://example.com/photo.jpg"
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| text | string | Yes | min:1, max:5000 |
| image_url | string | No | valid URL format |

**Response (201 Created):**
```json
{
  "data": {
    "id": 42,
    "user_id": 1,
    "username": "anika",
    "text": "Just had an amazing conversation with a stranger at the coffee shop. No phones, just real talk.",
    "image_url": "https://example.com/photo.jpg",
    "authenticity_score": 0.82,
    "embedding_status": "generated",
    "created_at": "2026-07-07T14:30:00Z"
  },
  "meta": {}
}
```

**Failure cases:**
- `401` — No/invalid token
- `422` — Validation errors (missing text, invalid URL)
- `201` with `"embedding_status": "pending"` — Python service unreachable, post saved without embedding

---

### Endpoint 2: Get Feed

**`GET /api/feed?page=1&per_page=20`**

Returns a personalized, ranked feed for the authenticated user.

**Query Parameters:**

| Param | Type | Default | Validation |
|-------|------|---------|------------|
| page | integer | 1 | min:1 |
| per_page | integer | 20 | min:1, max:50 |

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 15,
      "user_id": 2,
      "username": "ravi",
      "avatar_url": "https://example.com/ravi.jpg",
      "text": "Walked home in the rain without an umbrella today. Felt alive.",
      "image_url": null,
      "authenticity_score": 0.91,
      "created_at": "2026-07-07T10:15:00Z",
      "time_ago": "4 hours ago",
      "has_reacted": true
    },
    {
      "id": 23,
      "user_id": 1,
      "username": "anika",
      "text": "Sometimes the best filter is no filter at all.",
      "image_url": "https://example.com/sunset-raw.jpg",
      "authenticity_score": 0.85,
      "created_at": "2026-07-06T18:00:00Z",
      "time_ago": "20 hours ago",
      "has_reacted": false
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 47
  }
}
```

**Notes:**
- `has_reacted` indicates whether the authenticated user has already reacted to this post
- Posts are ranked using the feed algorithm (Section 5), not by chronological order
- All posts in the system are candidates (no social-graph pre-filtering)

---

### Endpoint 3: Search

**`GET /api/search?q=funny travel stories from last week`**

Semantic search across all posts using vector similarity.

**Query Parameters:**

| Param | Type | Required | Validation |
|-------|------|----------|------------|
| q | string | Yes | min:1, max:500 |

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 8,
      "user_id": 2,
      "username": "ravi",
      "avatar_url": "https://example.com/ravi.jpg",
      "text": "Got lost in Goa and ended up at this tiny beach bar where the owner told me his life story...",
      "image_url": null,
      "authenticity_score": 0.88,
      "similarity_score": 0.74,
      "created_at": "2026-07-01T09:00:00Z",
      "time_ago": "6 days ago",
      "has_reacted": false
    }
  ],
  "meta": {
    "count": 7,
    "threshold": 0.2
  }
}
```

**Notes:**
- Returns maximum 10 results
- Only returns posts where cosine similarity >= 0.2
- Searches all posts from all users (discovery tool, not limited to connections)
- Posts with NULL embedding are excluded from results
- No pagination — fixed top-10 results

---

### Endpoint 4: Log Interaction

**`POST /api/interactions`**

Records a user interaction and updates relationship depth.

**Request:**
```json
{
  "post_id": 15,
  "type": "reaction"
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| post_id | integer | Yes | exists in posts table |
| type | string | Yes | in: view, reply, reaction |

**Response (201 Created):**
```json
{
  "data": {
    "id": 156,
    "user_id": 1,
    "post_id": 15,
    "type": "reaction",
    "created_at": "2026-07-07T14:35:00Z"
  },
  "meta": {}
}
```

**Side effects (not in response):**
- `relationships` row for (user → post_author) is incremented by type weight (view=1, reaction=2, reply=3)
- User's `interest_vector` is updated: `new = 0.9 * old + 0.1 * post.embedding`

**Idempotency for reactions:**
- A user can only have one `reaction` per post. Sending a second `reaction` to the same post removes it (toggle behavior) and returns `200` with `{ "data": { "action": "removed" } }`
- `view` interactions are not deduplicated (multiple views are valid)

---

## 5. Feed Ranking Algorithm

### Philosophy

The Real Connections Feed inverts traditional social media ranking. Instead of optimizing for engagement (likes, shares, time-on-app), it optimizes for **genuine human connection**. The algorithm asks: "Is this post authentic? Is it from someone you actually connect with? Is it about something you care about? Is it timely?"

### The Formula

```
score = (0.25 × authenticity) + (0.35 × relationship_depth) + (0.25 × semantic_similarity) - (0.15 × time_decay)
```

All input signals are normalized to [0.0, 1.0] before weighting.

### Signal Breakdown

#### Signal 1: Authenticity Score (weight: 0.25)

Computed once at post creation time. Immutable after that.

**Image signals (45% of authenticity score):**

| Sub-signal | Weight | Logic |
|------------|--------|-------|
| Filter detection | 0.25 | Placeholder: returns 1.0 (no image) or 0.8 (has image). Future: CV model detects Instagram-style filters (saturation boost, vignettes, color grading). Lower score = more filters detected. |
| Retouching detection | 0.20 | Placeholder: returns 1.0 (no image) or 0.8 (has image). Future: detect FaceTune artifacts, skin smoothing, face reshaping. |

**Text signals (55% of authenticity score):**

| Sub-signal | Weight | Logic |
|------------|--------|-------|
| Text length | 0.15 | Optimal: 50–500 chars → 1.0. Under 50 → scales linearly from 0.3. Over 500 → gradual decay to 0.5 at 2000+ chars. |
| Hashtag density | 0.15 | 0 hashtags → 1.0. 1-2 → 0.9. 3-5 → 0.6. >5 → 0.2. |
| Excessive caps | 0.10 | <10% uppercase → 1.0. 10-30% → linear decay. >30% → 0.3. |
| URL spam | 0.10 | 0 URLs → 1.0. 1 URL → 0.9. 2 → 0.7. >2 → 0.3. |
| Has original text | 0.05 | Has text → 1.0. Image-only post (empty text) → 0.4. |

**Pseudocode:**
```
function computeAuthenticityScore(text, imageUrl):
    // Image signals (placeholders)
    filterScore = imageUrl ? 0.8 : 1.0
    retouchScore = imageUrl ? 0.8 : 1.0

    // Text signals
    lengthScore = scoreLengthCurve(len(text))
    hashtagScore = scoreHashtags(countHashtags(text))
    capsScore = scoreCaps(uppercaseRatio(text))
    urlScore = scoreUrls(countUrls(text))
    hasTextScore = len(text.trim()) > 0 ? 1.0 : 0.4

    // Weighted sum
    score = (0.25 * filterScore)
          + (0.20 * retouchScore)
          + (0.15 * lengthScore)
          + (0.15 * hashtagScore)
          + (0.10 * capsScore)
          + (0.10 * urlScore)
          + (0.05 * hasTextScore)

    return clamp(score, 0.0, 1.0)
```

#### Signal 2: Relationship Depth (weight: 0.35)

Measures how genuinely the viewer connects with the post's author. Directional — A's depth toward B is independent of B's depth toward A.

**Stored in**: `relationships.score` (materialized, updated on each interaction)

**Interaction weights:**
| Type | Weight | Rationale |
|------|--------|-----------|
| View | 1 | Passive but shows interest when repeated |
| Reaction | 2 | Intentional, low effort |
| Reply | 3 | Active effort, strongest signal of genuine connection |

**Normalization**: Raw score is capped at 100 and divided to produce [0.0, 1.0]:
```
normalized_depth = min(raw_score, 100) / 100
```

A user with no interactions toward an author has relationship_depth = 0.0. They can still see that author's posts — the 0.0 score just means the post won't get a relationship boost.

#### Signal 3: Semantic Similarity (weight: 0.25)

Measures how topically aligned a post is with the viewer's interests.

**Computed as**: Cosine similarity between `users.interest_vector` and `posts.embedding`

```
semantic_similarity = cosine_sim(user.interest_vector, post.embedding)
```

- Range: [-1.0, 1.0] in theory, but almost always [0.0, 1.0] for text embeddings
- If user has no `interest_vector` (NULL — new user): defaults to 0.5 (neutral)
- If post has no `embedding` (NULL — service was down): defaults to 0.0

**User interest vector update** (on each interaction):
```
user.interest_vector = 0.9 * user.interest_vector + 0.1 * post.embedding
```

This exponential moving average naturally weights recent interests higher without storing interaction history.

#### Signal 4: Time Decay (weight: 0.15)

Ensures freshness without dominating relevance. A great post from 3 days ago should still beat a mediocre post from 1 hour ago.

**Function**: Exponential decay

```
time_decay = 1 - e^(-0.02 * age_in_hours)
```

| Post Age | Decay Value | Effect |
|----------|-------------|--------|
| 1 hour | 0.02 | Almost no penalty |
| 6 hours | 0.11 | Minor penalty |
| 24 hours | 0.38 | Moderate penalty |
| 3 days | 0.76 | Significant, but strong signals override |
| 7 days | 0.96 | Nearly full penalty — effectively buried |

This is **subtracted** from the score, so newer posts have a slight advantage.

### Complete Pseudocode

```
function generateFeed(viewer, page, perPage):
    // Step 1: Get all candidate posts
    candidates = Post.whereNotNull('created_at')
                     .orderBy('created_at', 'desc')
                     .limit(500)  // Performance guard: score at most 500 recent posts
                     .get()

    // Step 2: Score each candidate
    scoredPosts = []
    for post in candidates:
        // Authenticity (already computed and stored)
        authenticity = post.authenticity_score

        // Relationship depth
        relationship = Relationship.find(viewer.id, post.user_id)
        depth = relationship ? min(relationship.score, 100) / 100 : 0.0

        // Semantic similarity
        if viewer.interest_vector AND post.embedding:
            similarity = cosineSimilarity(viewer.interest_vector, post.embedding)
        else:
            similarity = 0.5 if viewer.interest_vector is NULL else 0.0

        // Time decay
        ageHours = hoursSince(post.created_at)
        decay = 1 - exp(-0.02 * ageHours)

        // Final score
        score = (0.25 * authenticity)
              + (0.35 * depth)
              + (0.25 * similarity)
              - (0.15 * decay)

        scoredPosts.append({ post, score })

    // Step 3: Sort and paginate
    scoredPosts.sortByDescending('score')
    offset = (page - 1) * perPage
    return scoredPosts.slice(offset, offset + perPage)
```

### Why These Weights?

| Signal | Weight | Justification |
|--------|--------|---------------|
| Relationship depth | 0.35 (highest) | The product's name is "Real Connections" — the core promise is surfacing content from people you genuinely connect with |
| Authenticity | 0.25 | The brand's differentiator — fewer filters, genuine expression. Important, but acts more as a hygiene factor (most genuine posts score similarly) |
| Semantic similarity | 0.25 | Keeps the feed relevant to user interests. Without this, you'd see all posts from close connections regardless of topic |
| Time decay | 0.15 (lowest) | "Newer is preferred but not at the expense of relevance" — this is explicitly the lowest priority per the brief |

---

## 6. AI Agentic Tools Used

### Tool: Claude Code (Claude Opus)

**How it was used throughout this project:**

#### Phase 1: Architecture & Planning
- **Grilling session**: Used Claude Code to conduct a structured decision-making interview, walking through each architectural choice one by one. This forced me to justify every decision and surface trade-offs early rather than discovering them mid-implementation.
- **Domain modeling**: Sharpened definitions of "authenticity score," "relationship depth," and "semantic similarity" from vague concepts into precise, implementable specifications.
- **Trade-off analysis**: Claude helped enumerate alternatives (pgvector vs. Pinecone, sync vs. async embeddings) and reason about the MVP constraints.

#### Phase 2: Implementation
- **Code generation**: Used Claude Code to scaffold Laravel controllers, services, migrations, and the Python FastAPI service. Each generated file was reviewed and customized.
- **Feed algorithm implementation**: Translated the pseudocode from this TSD into working PHP, with Claude suggesting optimizations (e.g., batch-loading relationships instead of N+1 queries).
- **Test writing**: Generated PHPUnit feature tests with appropriate mocking of the embedding service.
- **React Native UI**: Scaffolded the feed screen with proper state management, styled to match the brand direction.

#### Phase 3: Documentation
- **This TSD**: Written collaboratively with Claude Code. I made the decisions; Claude helped structure them clearly and catch inconsistencies.
- **SQL queries**: Used Claude for initial drafts, then verified correctness against the schema.

### Why AI Tools Mattered Here

The 8-hour time constraint makes AI tools non-optional. My workflow:
1. **Think** — Make the architectural decision myself (with AI as a sparring partner)
2. **Generate** — Let Claude produce the boilerplate and initial implementation
3. **Review & Refine** — Catch edge cases, adjust naming, ensure convention compliance
4. **Test** — Verify the output actually works

This is not "copy-paste from ChatGPT." It's agentic collaboration — I directed the architecture; AI accelerated the execution.

---

## 7. Trade-offs & Assumptions

### Trade-offs Made

| Decision | Trade-off | Why We Accepted It |
|----------|-----------|-------------------|
| pgvector over Pinecone | Less scalable past ~1M vectors | MVP won't hit this. Migration path exists. Simpler setup for evaluator. |
| Synchronous embedding | Post creation blocked by Python service | Latency is <50ms locally. Graceful fallback if service is down. Avoids queue infrastructure. |
| All posts as candidates | Feed computation scans more posts | At <100K posts, this is fast. Avoids cold-start problem for new users. |
| Materialized relationship score | Score grows monotonically (no decay) | Acceptable for MVP. Decay can be added via a cron job later. |
| Offset pagination | Possible duplicates between pages | Simpler implementation. Feed re-ranks on every request anyway. |
| Single heart reaction | Limited expression | Matches brand ethos of simplicity. Brief says "reaction button" (singular). |
| Image authenticity placeholders | Not actually detecting filters | Properly architectured for future CV model. Returns reasonable defaults. Shows we understood the brief. |

### Assumptions

1. **Scale**: <100K posts, <10K users. This informs choices about pgvector, full-candidate scoring, and no caching layer.
2. **Single server**: The Python embedding service runs on the same machine as Laravel (localhost:8001). In production, it would be behind a load balancer.
3. **No image uploads**: Posts reference images by URL. Actual file upload and CDN are out of scope.
4. **No real-time**: Feed is request-driven, not WebSocket-pushed. User pulls to refresh.
5. **Test environment**: The evaluator has PostgreSQL 16+ with pgvector extension available. Docker instructions provided as a fallback.
6. **Embedding model availability**: `all-MiniLM-L6-v2` downloads on first run (~80MB). If the evaluator's network blocks this, the deterministic mock fallback activates automatically.
7. **Two seeded users are sufficient**: The brief requires minimum 2. Our seed data creates meaningful interaction history between them to demonstrate ranking behavior.

### What I'd Do Differently With More Time

1. **Redis caching**: Cache feed results per user with 30-60s TTL. Invalidate on new interactions.
2. **Background embedding backfill**: Queue job to generate embeddings for posts that were created while the Python service was down.
3. **Real image authenticity**: Integrate a lightweight CNN (or EXIF metadata analysis) to detect filters and retouching.
4. **Relationship decay**: Daily cron that multiplies all relationship scores by 0.95 — connections that aren't maintained gradually fade.
5. **A/B testing framework**: Expose ranking weights as config, run experiments on which balance produces the most "authentic" engagement.
6. **Content moderation**: Flag posts with toxic content before they enter the feed.
7. **Cursor-based pagination**: Snapshot the feed at request time and paginate through a stable result set.

---

## Appendix: Quick-Reference Summary

| Aspect | Decision |
|--------|----------|
| Database | PostgreSQL 16 + pgvector 0.7 |
| Vector dimensions | 384 (all-MiniLM-L6-v2) |
| Auth | Laravel Sanctum, Bearer tokens |
| Feed candidates | All posts (no pre-filter) |
| Ranking weights | relationship=0.35, authenticity=0.25, semantic=0.25, time_decay=0.15 |
| Time decay | Exponential, λ=0.02, ~7 day effective lifespan |
| Relationship depth | Directional, materialized, weighted (view=1, reaction=2, reply=3), cap=100 |
| User interests | 384-dim vector on users table, EMA update (0.9/0.1) |
| Search | Pure cosine similarity, top 10, threshold ≥ 0.2 |
| Pagination | Offset-based, 20 per page |
| Embedding call | Synchronous, graceful NULL fallback |
| Image authenticity | Placeholder scores, architected for real CV model |
