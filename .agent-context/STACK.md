# Technology Stack

## Backend — Laravel PHP

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Framework | Laravel | 11.x | Main API layer, routing, auth, ORM |
| Auth | Laravel Sanctum | 4.x | Token-based authentication for mobile |
| Database | PostgreSQL | 16+ | Primary relational data store |
| Vector Extension | pgvector | 0.7+ | Vector similarity search (cosine distance `<=>`) |
| ORM | Eloquent | (bundled) | Database queries and relationships |
| Testing | PHPUnit | (bundled) | Feature tests with RefreshDatabase |
| pgvector PHP | pgvector/pgvector (composer) | 0.2+ | Laravel vector column support |

## Backend — Python Embedding Service

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Framework | FastAPI | 0.100+ | Lightweight HTTP service for embeddings |
| Embedding Model | sentence-transformers | 2.x | all-MiniLM-L6-v2 (384-dim vectors) |
| Server | Uvicorn | 0.27+ | ASGI server |
| Validation | Pydantic | 2.x | Request/response models |

## Mobile — React Native

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Framework | React Native | 0.74+ | Cross-platform mobile UI |
| Tooling | Expo | SDK 51+ | Fast development, managed workflow |
| HTTP | axios | 1.x | API communication |
| Date Formatting | date-fns | 3.x | "time ago" display |
| Navigation | (single screen) | — | Not needed for this assessment |

## Database

| Store | Technology | Purpose |
|-------|-----------|---------|
| Primary DB | PostgreSQL 16 | Users, posts, interactions, relationships |
| Vector Store | pgvector (PG extension) | Post embeddings (384-dim), cosine similarity search |

**NOT used (out of scope for MVP):**
- Redis (caching)
- Queue/worker infrastructure
- Docker (optional convenience only)

## Dev Tools

| Tool | Purpose |
|------|---------|
| Composer | PHP dependency management |
| pip / venv | Python dependency management |
| npm | JS dependency management |
| Claude Code | AI-assisted development (documented in TSD) |

## Why This Stack

- **pgvector over Pinecone**: Keeps everything in one database — simpler ops, no network latency for vector queries, free. Trade-off: less scalable past ~1M vectors.
- **FastAPI over Flask**: Async, auto-generates OpenAPI docs, type-safe with Pydantic.
- **all-MiniLM-L6-v2 over OpenAI embeddings**: Free, runs locally, no API key needed, 384-dim is compact yet effective for semantic search.
- **Expo over bare React Native**: Faster to scaffold a single-screen demo. No native module linking needed.
- **PostgreSQL over MySQL**: pgvector only works with PostgreSQL. Also better for JSON, arrays, and advanced queries.
- **Offset pagination over cursor**: Simpler implementation for MVP. Feed re-ranks on every request anyway.
- **Synchronous embedding over async queue**: <50ms latency locally, avoids Redis/queue infrastructure, graceful NULL fallback if service is down.
