# Architecture

## System Overview

Guised Up is a social platform with a "Real Connections Feed" that ranks content by authenticity, relationship depth, semantic similarity, and time decay — not engagement metrics.

## High-Level Architecture

```
┌─────────────────────┐
│   React Native App  │
│   (Expo / Mobile)   │
└─────────┬───────────┘
          │ HTTPS (Bearer Token)
          ▼
┌─────────────────────┐
│   Laravel PHP API   │
│   (Sanctum Auth)    │
│                     │
│  • POST /api/posts  │
│  • GET  /api/feed   │
│  • GET  /api/search │
│  • POST /api/inter  │
└────┬──────────┬─────┘
     │          │ HTTP (internal, port 8001)
     ▼          ▼
┌─────────┐  ┌──────────────────┐
│PostgreSQL│  │ Python Embedding │
│+ pgvector│  │ Service (FastAPI)│
│          │  │                  │
│ • users  │  │ • all-MiniLM-L6  │
│ • posts  │  │ • /embed endpoint│
│ • inter  │  │ • 384-dim output │
│ • relat  │  └──────────────────┘
└──────────┘
```

## Request Flows

### Creating a Post
1. Mobile app sends `POST /api/posts` with text + optional image URL
2. Laravel validates input via `CreatePostRequest`
3. `AuthenticityScoreService` computes score (45% image signals + 55% text signals)
4. `EmbeddingService` calls Python service synchronously
5. Python returns 384-dim vector embedding
6. Post stored with embedding + authenticity_score
7. If Python service is DOWN: post stored with `embedding = NULL`, returns 201 with `"embedding_status": "pending"`

### Fetching Feed
1. Mobile app sends `GET /api/feed?page=N`
2. `FeedRankingService` fetches ALL candidate posts (no social-graph pre-filter — all posts are candidates)
3. Applies performance guard: limit to 500 most recent posts
4. Scores each candidate: `score = 0.25*authenticity + 0.35*relationship_depth + 0.25*semantic_similarity - 0.15*time_decay`
5. All signals normalized to [0, 1] before weighting
6. Sorts by score descending, returns paginated results (20 per page, offset-based)

### Semantic Search
1. Mobile app sends `GET /api/search?q=query`
2. `EmbeddingService` forwards query text to Python service, gets 384-dim vector
3. Laravel queries pgvector using cosine distance operator (`<=>`)
4. Returns top 10 results where cosine similarity >= 0.2
5. Posts with NULL embedding are excluded
6. No time filter, no social-graph filter — pure vector similarity

### Logging Interactions
1. Mobile app sends `POST /api/interactions` with post_id and type
2. Validates type is one of: `view`, `reply`, `reaction`
3. **Reaction toggle**: if user already has a reaction on this post, remove it instead (return `{ "action": "removed" }`)
4. Stores interaction in `interactions` table
5. Updates `relationships` table: increment score by weight (view=1, reaction=2, reply=3)
6. Updates user's `interest_vector`: `new = 0.9 * old + 0.1 * post.embedding` (exponential moving average)

## Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| pgvector over Pinecone/Qdrant | Single database, no extra infra, sufficient at MVP scale (<100K posts) |
| Python as a sidecar service | Laravel lacks native ML libs; Python handles embedding generation cleanly |
| Weighted linear ranking | Interpretable, tunable weights, fast to compute — no black-box ML needed |
| Sanctum over Passport | Simpler token auth for mobile, less overhead |
| All posts as candidates | Avoids cold-start for new users; ranking does the filtering |
| Synchronous embedding | <50ms latency, avoids queue/Redis infrastructure |
| Materialized relationships | Avoids expensive aggregation on every feed request |
| String type column (not enum) | Easier to extend without DB migration |
| Offset pagination | Simple, matches spec "20 per page" language |

## Feed Ranking Signals

### Authenticity Score (weight: 0.25)
Computed once at post creation. Immutable.
- Image filter detection: 0.25 weight (placeholder: 1.0 no image, 0.8 with image)
- Image retouching: 0.20 weight (placeholder: 1.0 no image, 0.8 with image)
- Text length: 0.15 weight (50–500 chars optimal)
- Hashtag density: 0.15 weight (≤2 normal, >5 penalizes)
- Excessive caps: 0.10 weight (>30% uppercase = clickbait)
- URL spam: 0.10 weight (>2 URLs penalizes)
- Has original text: 0.05 weight (image-only penalized)

### Relationship Depth (weight: 0.35)
Directional, materialized in `relationships` table.
- view=1, reaction=2, reply=3 (increment weights)
- Normalized: `min(raw_score, 100) / 100`
- No decay for MVP

### Semantic Similarity (weight: 0.25)
Cosine similarity between `users.interest_vector` and `posts.embedding`.
- NULL interest_vector (new user) → defaults to 0.5
- NULL post embedding → defaults to 0.0
- Updated on each interaction via EMA: `0.9 * old + 0.1 * post.embedding`

### Time Decay (weight: 0.15)
Exponential: `1 - e^(-0.02 * age_in_hours)`
- 1h = 0.02, 24h = 0.38, 3 days = 0.76, 7 days = 0.96
- Subtracted from score

## Error Handling

- **Python service down during post creation**: Post saved with NULL embedding, returns 201 with `"embedding_status": "pending"`. Post appears in feed (semantic = 0) but not in search.
- **Unauthenticated requests**: Return 401
- **Validation errors**: Return 422 with field-specific messages
- **Reaction deduplication**: Second reaction on same post removes it (toggle)

## Scalability Notes (Future, Not MVP)

- pgvector works well up to ~1M vectors; beyond that, consider migrating to Qdrant
- Embedding service is stateless — horizontally scalable behind a load balancer
- Feed computation can be cached per user with short TTL (30-60s) using Redis
- Relationship scores can have decay via a daily cron (multiply by 0.95)
- Cursor-based pagination for stable feed scrolling
