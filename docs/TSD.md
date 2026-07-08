# Technical Solution Document — Real Connections Feed

**Project**: Guised Up — Real Connections Feed  
**Author**: Anikait Sehwag  
**Date**: July 2026  
**Stack**: React Native (Expo) + Laravel 11 + Python FastAPI + PostgreSQL (pgvector)

---

## Table of Contents

**Part 1 — High-Level Design**
1. [At a Glance](#1-at-a-glance)
2. [System Architecture](#2-system-architecture)
3. [Feed Ranking Philosophy](#3-feed-ranking-philosophy)
4. [Trade-offs & Assumptions](#4-trade-offs--assumptions)
5. [AI Tools Used](#5-ai-tools-used)

**Part 2 — Low-Level Implementation**
6. [Database Schema Design](#6-database-schema-design)
7. [Vector Embedding Strategy](#7-vector-embedding-strategy)
8. [API Design](#8-api-design)
9. [Feed Ranking — Signal Details & Pseudocode](#9-feed-ranking--signal-details--pseudocode)

---

# Part 1 — High-Level Design

## 1. At a Glance

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

---

## 2. System Architecture

### Overview

The system is a three-tier architecture: a React Native mobile client talks to a Laravel API, which delegates embedding generation to a stateless Python microservice. All data — relational and vector — lives in one PostgreSQL database via the pgvector extension.

### Diagram

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

**Creating a post**: client sends `{ text, image_url? }` → Laravel validates → `AuthenticityScoreService` computes a score from text heuristics + image placeholders → `EmbeddingService` calls the Python service for a 384-dim embedding → post is stored with embedding and score → `201` returned.

**Fetching the feed**: client requests a page → `FeedRankingService` scores every candidate post using `score = 0.25*authenticity + 0.35*relationship_depth + 0.25*semantic_similarity − 0.15*time_decay` → sorted descending and paginated (20/page).

**Semantic search**: query text is embedded → compared against post embeddings via pgvector's cosine distance operator (`<=>`) → top 10 results with similarity ≥ 0.2 returned.

**Logging an interaction**: interaction stored in `interactions` → the `relationships` row for viewer → post author is incremented by interaction weight (view=1, reaction=2, reply=3) → the viewer's `interest_vector` is updated via exponential moving average (`0.9*old + 0.1*post_embedding`).

*(Full request/response contracts are in [Section 8](#8-api-design).)*

---

## 3. Feed Ranking Philosophy

The Real Connections Feed inverts traditional social ranking. Instead of optimizing for engagement (likes, shares, time-on-app), it optimizes for **genuine human connection**: is this post authentic, from someone you actually connect with, about something you care about, and reasonably timely?

**Formula:**
```
score = (0.25 × authenticity) + (0.35 × relationship_depth) + (0.25 × semantic_similarity) − (0.15 × time_decay)
```
All input signals are normalized to [0.0, 1.0] before weighting.

**Why these weights:**

| Signal | Weight | Why |
|--------|--------|-----|
| Relationship depth | 0.35 (highest) | The product's name is "Real Connections" — the core promise is surfacing content from people you genuinely connect with |
| Authenticity | 0.25 | The brand's differentiator, but acts more as a hygiene factor since most genuine posts score similarly |
| Semantic similarity | 0.25 | Keeps the feed relevant to interests; without it, you'd see all posts from close connections regardless of topic |
| Time decay | 0.15 (lowest) | "Newer is preferred but not at the expense of relevance" — explicitly the lowest priority per the brief |

*(Full signal-by-signal computation and pseudocode is in [Section 9](#9-feed-ranking--signal-details--pseudocode).)*

---

## 4. Trade-offs & Assumptions

### Trade-offs Made

| Decision | Trade-off | Why We Accepted It |
|----------|-----------|---------------------|
| pgvector over Pinecone | Less scalable past ~1M vectors | MVP won't hit this; simpler setup for evaluator; migration path exists |
| Synchronous embedding | Post creation blocked by Python service | <50ms latency locally; graceful fallback if service is down; avoids queue infra |
| All posts as candidates | Feed computation scans more posts as volume grows | Fast at <100K posts; avoids cold-start problem for new users |
| Materialized relationship score | Grows monotonically, no decay | Acceptable for MVP; decay can be added via cron later |
| Offset pagination | Possible duplicates between pages | Simpler implementation; feed re-ranks on every request anyway |
| Single heart reaction | Limited expression | Matches brand ethos of simplicity; brief specifies "reaction button" (singular) |
| Image authenticity placeholders | Doesn't actually detect filters | Properly architected for a future CV model; returns reasonable defaults |

### Assumptions

1. **Scale**: <100K posts, <10K users — informs pgvector, full-candidate scoring, and no caching layer
2. **Single server**: the Python embedding service runs alongside Laravel on localhost:8001; would sit behind a load balancer in production
3. **No image uploads**: posts reference images by URL; file upload and CDN are out of scope
4. **No real-time**: feed is request-driven (pull-to-refresh), not WebSocket-pushed
5. **Test environment**: assumes PostgreSQL 16+ with pgvector available; Docker instructions provided as fallback
6. **Embedding model availability**: `all-MiniLM-L6-v2` downloads on first run (~80MB); a deterministic mock activates automatically if the evaluator's network blocks this
7. **Two seeded users are sufficient** to demonstrate ranking behavior, matching the brief's stated minimum

### What I'd Do Differently With More Time

- **Redis caching** for feed results (30–60s TTL, invalidated on new interactions)
- **Background embedding backfill** for posts created while the Python service was down
- **Real image authenticity** via a lightweight CNN or EXIF metadata analysis
- **Relationship decay** — a daily cron that fades scores for unmaintained connections
- **A/B testing framework** to experiment with ranking weights
- **Content moderation** to flag toxic posts before they enter the feed
- **Cursor-based pagination** for a stable, snapshot-based result set

---

## 5. AI Tools Used

Different tools were used for different phases of this project, matched to what each does best:

**Architecture & Planning — Claude Opus**
- *Grilling session*: run through the grill-with-docs plugin as a one-on-one interview — a structured back-and-forth that walked through each architectural choice one by one, forcing me to justify every decision and surface trade-offs early rather than discovering them mid-implementation
- *Domain modeling*: Claude Opus sharpened "authenticity score," "relationship depth," and "semantic similarity" from vague concepts into precise, implementable specs
- *Trade-off analysis*: Claude Opus helped enumerate alternatives (pgvector vs. Pinecone, sync vs. async embeddings) and reason through the MVP constraints

**Implementation — Claude Sonnet, Claude Code, Kimi, Antigravity, Docker's AI tool**
- Scaffolding Laravel controllers, services, migrations, and the Python FastAPI service
- Translating the ranking pseudocode into working PHP, including optimizations like batch-loading relationships instead of N+1 queries
- Generating PHPUnit feature tests with embedding-service mocks, and scaffolding the React Native feed screen
- Docker's built-in AI tool assisted with containerizing the Laravel + Python services and debugging the local Compose setup

**Documentation & Everything Else — Gemini, Claude Haiku, ChatGPT**
- Drafting this TSD and initial SQL queries, later verified against the schema

**Workflow**: think through the decision myself (AI as a sparring partner) → generate boilerplate and initial implementation with AI → review and refine for edge cases and convention compliance → test that the output actually works. I directed the architecture; AI accelerated the execution.

---

# Part 2 — Low-Level Implementation Details

## 6. Database Schema Design

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

- **Single `interactions` table** for all types (view/reply/reaction) rather than separate tables — simpler schema, one index handles aggregation queries
- **Materialized `relationships` table** avoids expensive COUNT/SUM on every feed request; updated incrementally on each interaction
- **`interest_vector` on users** rather than a separate table — always fetched with the user, one-to-one, avoids an extra JOIN on feed generation
- **No soft deletes** — not required by the brief; hard deletes with foreign key cascades

---

## 7. Vector Embedding Strategy

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

**Why pgvector wins here**: already on PostgreSQL, so no added infrastructure; MVP scale (<100K posts) is comfortable with IVFFlat indexing; vector similarity can JOIN with relational data (relationships, timestamps) in a single query; simplest setup for an evaluator (`CREATE EXTENSION vector` and done).

**Migration path**: if the product scales past ~500K posts and search latency degrades, migrate vector data to Qdrant (open-source, self-hostable) while keeping relational data in PostgreSQL.

### Embedding Model: all-MiniLM-L6-v2

| Property | Value |
|----------|-------|
| Model | `sentence-transformers/all-MiniLM-L6-v2` |
| Output dimensions | 384 |
| Model size | ~80MB |
| Inference time | ~10–50ms per sentence (CPU) |
| Cost | Free, runs locally |
| Quality | Strong for semantic similarity; top performer on STSB benchmark for its size class |

**Why this model**: free with no API key, so an evaluator can run it immediately; 384 dimensions is compact (lower storage, faster similarity computation vs. 768/1536-dim models); CPU-only, no GPU required; well-suited to short social text (trained on sentence pairs).

**Fallback**: if the model can't load (low memory, CI environment), the embedding service returns a deterministic mock vector based on a hash of the input text, keeping the system always runnable and testable.

### How Embeddings Are Used

1. **Post creation**: text is embedded and stored in `posts.embedding`
2. **Search**: query text is embedded, then compared via cosine distance (`<=>`) against all post embeddings
3. **User interest**: on interaction, the post's embedding is blended into `users.interest_vector` via exponential moving average
4. **Feed ranking**: cosine similarity between `users.interest_vector` and each candidate `posts.embedding` produces the semantic_similarity signal

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

## 8. API Design

### Authentication

All endpoints require **Laravel Sanctum** (token-based) authentication.

- Token is obtained via seeder output (for demo) or a login endpoint (future)
- Sent as: `Authorization: Bearer {token}`
- Middleware: `auth:sanctum` on all `/api/*` routes
- Unauthenticated requests receive `401 Unauthorized`

### Response Format Convention

Successful responses:
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

**`POST /api/posts`** — creates a post, generates an embedding, computes an authenticity score.

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

**`GET /api/feed?page=1&per_page=20`** — returns a personalized, ranked feed for the authenticated user.

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

**Notes**: `has_reacted` indicates whether the authenticated user already reacted to this post. Posts are ranked using the feed algorithm ([Section 9](#9-feed-ranking--signal-details--pseudocode)), not chronological order. All posts in the system are candidates — no social-graph pre-filtering.

---

### Endpoint 3: Search

**`GET /api/search?q=funny travel stories from last week`** — semantic search across all posts using vector similarity.

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

**Notes**: max 10 results; only posts with cosine similarity ≥ 0.2; searches all users' posts (discovery, not limited to connections); posts with NULL embedding excluded; no pagination, fixed top-10.

---

### Endpoint 4: Log Interaction

**`POST /api/interactions`** — records a user interaction and updates relationship depth.

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

**Side effects (not in response)**: the `relationships` row for (user → post_author) is incremented by type weight (view=1, reaction=2, reply=3); the user's `interest_vector` is updated as `new = 0.9 * old + 0.1 * post.embedding`.

**Idempotency for reactions**: a user can only have one `reaction` per post — sending a second `reaction` to the same post removes it (toggle behavior) and returns `200` with `{ "data": { "action": "removed" } }`. `view` interactions are not deduplicated.

---

## 9. Feed Ranking — Signal Details & Pseudocode

### Signal 1: Authenticity Score (weight: 0.25)

Computed once at post creation time; immutable after that.

**Image signals (45% of authenticity score):**

| Sub-signal | Weight | Logic |
|------------|--------|-------|
| Filter detection | 0.25 | Placeholder: 1.0 (no image) or 0.8 (has image). Future: CV model detects filters (saturation, vignettes, color grading) — lower score = more filters |
| Retouching detection | 0.20 | Placeholder: 1.0 (no image) or 0.8 (has image). Future: detect FaceTune artifacts, skin smoothing, reshaping |

**Text signals (55% of authenticity score):**

| Sub-signal | Weight | Logic |
|------------|--------|-------|
| Text length | 0.15 | Optimal 50–500 chars → 1.0; under 50 scales linearly from 0.3; over 500 decays gradually to 0.5 at 2000+ chars |
| Hashtag density | 0.15 | 0 → 1.0. 1–2 → 0.9. 3–5 → 0.6. >5 → 0.2 |
| Excessive caps | 0.10 | <10% uppercase → 1.0. 10–30% → linear decay. >30% → 0.3 |
| URL spam | 0.10 | 0 URLs → 1.0. 1 → 0.9. 2 → 0.7. >2 → 0.3 |
| Has original text | 0.05 | Has text → 1.0. Image-only post (empty text) → 0.4 |

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

### Signal 2: Relationship Depth (weight: 0.35)

Measures how genuinely the viewer connects with the post's author. Directional — A's depth toward B is independent of B's depth toward A. Stored in `relationships.score` (materialized, updated on each interaction).

| Type | Weight | Rationale |
|------|--------|-----------|
| View | 1 | Passive but shows interest when repeated |
| Reaction | 2 | Intentional, low effort |
| Reply | 3 | Active effort, strongest signal of genuine connection |

**Normalization**: raw score capped at 100, divided to [0.0, 1.0]:
```
normalized_depth = min(raw_score, 100) / 100
```
A user with no interactions toward an author has relationship_depth = 0.0 — they can still see that author's posts, it just gets no relationship boost.

### Signal 3: Semantic Similarity (weight: 0.25)

Cosine similarity between `users.interest_vector` and `posts.embedding`:
```
semantic_similarity = cosine_sim(user.interest_vector, post.embedding)
```
Range is [-1.0, 1.0] in theory, but almost always [0.0, 1.0] for text embeddings. Defaults to 0.5 (neutral) if the user has no `interest_vector`; defaults to 0.0 if the post has no `embedding`.

**User interest vector update** (on each interaction):
```
user.interest_vector = 0.9 * user.interest_vector + 0.1 * post.embedding
```
This EMA naturally weights recent interests higher without storing interaction history.

### Signal 4: Time Decay (weight: 0.15)

Ensures freshness without dominating relevance — a great post from 3 days ago should still beat a mediocre post from 1 hour ago.

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
