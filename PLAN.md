# Guised Up — Take-Home Project Execution Plan

## Overview

Build a "Real Connections Feed" for a social platform that ranks content by authenticity, relationship depth, semantic similarity, and time decay — NOT engagement metrics. Deliverables: Technical Solution Doc, Laravel+Python backend, React Native feed screen, and SQL queries.

---

## Decisions Made (Grilling Session)

These decisions are final. All implementation must follow them.

### Feed Candidate Pool
- **All posts are candidates** — no social-graph pre-filtering
- Ranking algorithm does the filtering, not the query
- Avoids cold-start problem for new users

### Authenticity Score (computed once at post creation, immutable)
- **45% image signals / 55% text signals**
- Image filter detection: weight 0.25 (placeholder: returns 1.0 if no image, 0.8 if has image)
- Image retouching detection: weight 0.20 (placeholder: returns 1.0 if no image, 0.8 if has image)
- Text length (50–500 chars optimal): weight 0.15
- Hashtag density (≤2 is normal, >5 penalizes): weight 0.15
- Excessive caps (>30% uppercase = clickbait): weight 0.10
- URL spam (>2 URLs penalizes): weight 0.10
- Has original text (image-only posts penalized): weight 0.05

### Relationship Depth
- **Directional** — A→B is independent of B→A
- **Materialized** in `relationships` table, updated on each interaction
- Interaction weights: view=1, reaction=2, reply=3
- Normalized: `min(raw_score, 100) / 100` for ranking
- No decay for MVP

### Semantic Similarity
- **User interest vector** stored as 384-dim column on `users` table
- Updated via exponential moving average: `new = 0.9 * old + 0.1 * post.embedding`
- NULL interest_vector (new user) → defaults to 0.5 similarity
- NULL post embedding → defaults to 0.0

### Time Decay
- **Exponential decay**: `1 - e^(-0.02 * age_in_hours)`
- ~7 day effective lifespan for posts
- 1h = 0.02 penalty, 24h = 0.38, 3 days = 0.76, 7 days = 0.96

### Ranking Formula
```
score = (0.25 × authenticity) + (0.35 × relationship_depth) + (0.25 × semantic_similarity) - (0.15 × time_decay)
```
- Relationship depth has highest weight (0.35) — core product differentiator
- All signals normalized to [0, 1] before weighting

### Search
- Pure vector similarity, no time parsing
- All posts from all users (discovery tool)
- Top 10 results, minimum similarity threshold ≥ 0.2
- Posts with NULL embedding excluded

### Interaction Types
- String column validated at app layer: `['view', 'reply', 'reaction']`
- NOT a database-level enum (easier to extend)

### Pagination
- Offset-based: `?page=1&per_page=20`
- Response meta: `{ page, per_page, total }`

### Embedding Generation
- **Synchronous** on post creation (latency ~10-50ms)
- Graceful fallback: if Python service is down, post saved with NULL embedding
- Returns 201 with `"embedding_status": "pending"`
- Post still appears in feed (semantic_similarity = 0), excluded from search

### Auth & Seeding
- Laravel Sanctum, Bearer tokens
- Two users: Anika (anika@guisedup.com) + Ravi (ravi@guisedup.com)
- 10–15 posts split between both, varied authenticity scores
- Pre-logged interactions: Anika has interacted with Ravi's posts
- Pre-computed interest vector on Anika
- Mocked deterministic vectors for seeded posts
- API tokens output to console on seed

### Reaction Button
- Single heart button, toggleable
- Tapping logs `POST /api/interactions` with `type: 'reaction'`
- Second tap on same post removes the reaction (toggle)
- Views logged automatically on viewport entry (FlatList onViewableItemsChanged)
- Reply UI out of scope (seeded in DB only)

### Error Handling — Python Service Down
- Post creation returns 201 (success) — post saved without embedding
- `authenticity_score` still computed (text heuristics don't need embedding service)
- Post appears in feed but not in search results
- Log warning for ops visibility

### SQL Queries
- PostgreSQL syntax (not ANSI-generic)
- D1 counts raw interactions equally (NOT weighted) — weights only apply to relationship_depth

### React Native — Visual Direction
- Primary: `#E8613C` (terracotta orange)
- Background: `#FDF8F4` (warm off-white)
- Card background: `#FFFFFF`
- Text primary: `#2D2D2D`
- Text secondary: `#8C8C8C`
- Border/divider: `#F0E8E2`
- Accent: `#D4A574` (warm tan)
- Rounded cards (border-radius: 16), generous spacing (16px gaps)
- Circular avatar placeholders with warm-tone borders
- Subtle card shadows

### Out of Scope
- User registration/login UI (hardcoded token)
- Reply UI / comment thread
- Image upload (accept URL only)
- Push notifications
- User profile screen
- Redis caching
- Docker compose (optional, not required)
- Real image filter detection (placeholder only)
- Rate limiting

---

## Phase 1: Technical Solution Document (Part A) — COMPLETE ✓

**Output**: `/docs/TSD.md`

---

## Phase 2: Backend API (Part B) — ~3 hours — COMPLETE ✓

**Priority: HIGH (25% weight)**

### Step 2.1: Laravel Project Setup (~20 min)
- [x] Initialize Laravel 11 project with Sanctum
- [x] Configure PostgreSQL with pgvector extension
- [x] Create `.env.example`
- [x] Set up folder structure per `.agent-context/STRUCTURE.md`

### Step 2.2: Database Migrations (~30 min)
- [x] Enable pgvector extension migration
- [x] `create_users_table` — extend with avatar_url, interest_vector (vector(384))
- [x] `create_posts_table` — text, image_url, authenticity_score, embedding (vector(384))
- [x] `create_interactions_table` — user_id, post_id, type (varchar), created_at
- [x] `create_relationships_table` — user_id, target_user_id, score, unique constraint
- [x] Add all indexes (composite on interactions, IVFFlat on posts.embedding, etc.)

### Step 2.3: Python Embedding Service (~30 min)
- [x] Create FastAPI service with `/embed` endpoint
- [x] Load `all-MiniLM-L6-v2` model via sentence-transformers
- [x] Fallback: deterministic mock embeddings via hash if model unavailable
- [x] Pydantic request/response models
- [x] `requirements.txt` with pinned versions

### Step 2.4: API Endpoints (~60 min)
- [x] `POST /api/posts` — validate via CreatePostRequest, call EmbeddingService, compute AuthenticityScore, store, return PostResource
- [x] `GET /api/feed` — FeedRankingService scores all candidates, paginate, return with meta
- [x] `GET /api/search?q={query}` — embed query, cosine similarity via pgvector, top 10, threshold ≥ 0.2
- [x] `POST /api/interactions` — validate type, store interaction, update relationships.score, update user interest_vector, handle reaction toggle

### Step 2.5: Services (~30 min)
- [x] `AuthenticityScoreService` — 7 sub-signals with weights, returns clamped [0, 1]
- [x] `EmbeddingService` — HTTP call to Python service, graceful NULL on failure
- [x] `FeedRankingService` — candidate pool (limit 500 recent), score formula, sort, paginate

### Step 2.6: Seeders (~20 min)
- [x] `UserSeeder` — Anika + Ravi with hashed passwords, Sanctum tokens output to console
- [x] `PostSeeder` — 10-15 posts with varied text, some with hashtag spam, some genuine
- [x] Mock embeddings (deterministic 384-dim vectors)
- [x] Pre-computed interactions and relationship scores
- [x] Pre-computed interest_vector on Anika

### Step 2.7: Tests (~30 min)
- [x] `PostCreationTest` — happy path 201, unauthenticated 401, validation 422
- [x] `FeedTest` — paginated results with meta, requires auth, ranking order correct
- [x] `SearchTest` — returns results for valid query, empty for no match, 422 without q param
- [x] Mock EmbeddingService in all tests (return fixed 384-dim vector)
- [x] Use RefreshDatabase trait

---

## Phase 3: React Native Screen (Part C) — ~2 hours — COMPLETE ✓

**Priority: MEDIUM-HIGH (20% weight)**

### Step 3.1: Project Setup (~15 min)
- [x] Initialize Expo project
- [x] Install: axios, date-fns, react-native-safe-area-context

### Step 3.2: Theme & Styling (~15 min)
- [x] `theme.js` — color tokens, spacing scale, typography
- [x] Use decided palette (#E8613C primary, #FDF8F4 background, etc.)

### Step 3.3: Feed Screen UI (~60 min)
- [x] `FeedScreen.js` — FlatList with onEndReached for infinite scroll
- [x] `PostCard.js` — avatar circle, username, text, time_ago, heart button
- [x] `SearchBar.js` — debounced input, calls /api/search when active
- [x] Heart button: toggleable, logs reaction interaction on tap
- [x] View logging: onViewableItemsChanged fires POST /api/interactions with type='view'

### Step 3.4: Data Fetching (~30 min)
- [x] `api.js` — axios instance with base URL and Bearer token (hardcoded from seeder)
- [x] Feed: fetch page 1 on mount, append on infinite scroll
- [x] Search: swap feed data for search results when query active

### Step 3.5: Edge Cases & Polish (~15 min)
- [x] `LoadingState.js` — skeleton/activity indicator
- [x] `EmptyState.js` — "No posts yet — start connecting!"
- [x] `ErrorState.js` — retry button with message
- [x] Pull-to-refresh (RefreshControl)

---

## Phase 4: SQL Queries (Part D) — ~30 min

**Priority: MEDIUM (15% weight)**

- [x] **D1**: Top 10 most active users (last 7 days) — COUNT all interaction types equally, GROUP BY user_id, ORDER DESC LIMIT 10
- [x] **D2**: Posts from most-interacted-with users for a given user_id — subquery for interaction frequency per target user, JOIN posts, filter 30 days
- [x] **D3**: Posts with 100+ views but 0 reactions — conditional aggregation on interaction types, HAVING
- [x] **D4**: Spam detection — users with 20+ posts in 24h — GROUP BY user_id HAVING COUNT > 20, JOIN users for email

All queries use PostgreSQL syntax.

### Output
- `/sql/queries.sql`

---

## Phase 5: Final Assembly — ~1 hour — COMPLETE ✓

- [x] Write comprehensive `README.md` — setup instructions, how to run each part, .env.example
- [x] Review TSD for completeness and clarity
- [x] Ensure migrations are runnable from scratch
- [x] Git init, organize repo structure, push

---

## Repo Structure

```
guised-up-assessment/
├── README.md
├── .env.example
├── PLAN.md
├── CONTEXT.md
├── docs/
│   ├── TSD.md
│   └── adr/
├── .agent-context/
│   ├── ARCHITECTURE.md
│   ├── STACK.md
│   ├── STRUCTURE.md
│   ├── CONVENTIONS.md
│   └── TESTING.md
├── backend/
│   ├── laravel-api/
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   ├── PostController.php
│   │   │   │   │   ├── FeedController.php
│   │   │   │   │   ├── SearchController.php
│   │   │   │   │   └── InteractionController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   ├── CreatePostRequest.php
│   │   │   │   │   └── LogInteractionRequest.php
│   │   │   │   └── Resources/
│   │   │   │       └── PostResource.php
│   │   │   ├── Models/
│   │   │   │   ├── User.php
│   │   │   │   ├── Post.php
│   │   │   │   ├── Interaction.php
│   │   │   │   └── Relationship.php
│   │   │   └── Services/
│   │   │       ├── FeedRankingService.php
│   │   │       ├── EmbeddingService.php
│   │   │       └── AuthenticityScoreService.php
│   │   ├── database/
│   │   │   ├── migrations/
│   │   │   │   ├── 0001_enable_pgvector.php
│   │   │   │   ├── 0002_create_users_table.php
│   │   │   │   ├── 0003_create_posts_table.php
│   │   │   │   ├── 0004_create_interactions_table.php
│   │   │   │   └── 0005_create_relationships_table.php
│   │   │   ├── seeders/
│   │   │   │   ├── DatabaseSeeder.php
│   │   │   │   ├── UserSeeder.php
│   │   │   │   └── PostSeeder.php
│   │   │   └── factories/
│   │   │       ├── UserFactory.php
│   │   │       ├── PostFactory.php
│   │   │       └── InteractionFactory.php
│   │   ├── routes/
│   │   │   └── api.php
│   │   ├── tests/
│   │   │   └── Feature/
│   │   │       ├── PostCreationTest.php
│   │   │       ├── FeedTest.php
│   │   │       └── SearchTest.php
│   │   ├── composer.json
│   │   └── .env.example
│   │
│   └── python-embedding/
│       ├── main.py
│       ├── embedding_service.py
│       └── requirements.txt
│
├── mobile/
│   └── GuisedUpFeed/
│       ├── App.js
│       ├── app.json
│       ├── package.json
│       └── src/
│           ├── screens/
│           │   └── FeedScreen.js
│           ├── components/
│           │   ├── PostCard.js
│           │   ├── SearchBar.js
│           │   ├── LoadingState.js
│           │   ├── EmptyState.js
│           │   └── ErrorState.js
│           ├── services/
│           │   └── api.js
│           └── styles/
│               └── theme.js
│
└── sql/
    └── queries.sql
```

---

## Key Technical Decisions

| Decision | Choice | Why |
|----------|--------|-----|
| Vector DB | pgvector (PostgreSQL) | Single DB, no extra infra, sufficient for MVP scale |
| Embedding Model | all-MiniLM-L6-v2 | Free, runs locally, 384-dim is compact |
| Auth | Laravel Sanctum | Token-based, built for SPAs/mobile, zero config |
| RN Framework | Expo | Faster setup, good enough for single screen demo |
| Feed Algorithm | Weighted linear combination | Interpretable, tunable, fast to compute |
| Feed Candidates | All posts (no pre-filter) | Avoids cold-start, ranking does the filtering |
| Embedding Call | Synchronous with NULL fallback | <50ms latency, avoids queue infra |
| Relationship Storage | Materialized table | Avoids expensive aggregation on every feed request |
| Interaction Type | String column (not enum) | Easier to extend without migrations |
| Pagination | Offset-based | Simple, matches spec language |

---

## Time Budget

| Phase | Estimated | Status |
|-------|-----------|--------|
| Part A — TSD | 1.5h | COMPLETE ✓ |
| Part B — Backend | 3h | COMPLETE ✓ |
| Part C — React Native | 2h | COMPLETE ✓ |
| Part D — SQL | 0.5h | COMPLETE ✓ |
| Final Assembly | 1h | COMPLETE ✓ |
| **Total** | **8h** | |

---

## Execution Order

1. ~~TSD (informs all code decisions)~~ ✓
2. **Backend next** (feed screen depends on API)
3. SQL queries (quick, independent)
4. React Native screen (can mock API if backend isn't fully running)
5. Final polish and README
