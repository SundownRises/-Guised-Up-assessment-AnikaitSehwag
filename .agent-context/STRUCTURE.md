# Project Structure

```
guised-up-assessment/
│
├── README.md                    # Setup instructions, how to run, overview
├── .env.example                 # Environment variable template
├── PLAN.md                      # Execution plan with all decisions
├── CONTEXT.md                   # Domain glossary
│
├── .agent-context/              # Agent context files (this folder)
│   ├── ARCHITECTURE.md
│   ├── STACK.md
│   ├── STRUCTURE.md
│   ├── CONVENTIONS.md
│   └── TESTING.md
│
├── docs/
│   ├── TSD.md                   # Technical Solution Document (Part A) ✓ COMPLETE
│   └── adr/                     # Architectural Decision Records
│
├── backend/
│   ├── laravel-api/             # Laravel PHP application
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
│   └── python-embedding/        # Python embedding microservice
│       ├── main.py              # FastAPI app with /embed endpoint
│       ├── embedding_service.py # Model loading and inference + mock fallback
│       └── requirements.txt
│
├── mobile/
│   └── GuisedUpFeed/            # React Native (Expo) app
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
    └── queries.sql              # Raw SQL queries (D1-D4), PostgreSQL syntax
```

## Directory Purposes

| Directory | Purpose |
|-----------|---------|
| `docs/` | Technical Solution Document and any diagrams |
| `docs/adr/` | Architectural Decision Records (created lazily) |
| `backend/laravel-api/` | Main API — handles auth, routing, business logic, DB |
| `backend/python-embedding/` | Stateless microservice for generating text embeddings |
| `mobile/GuisedUpFeed/` | React Native feed screen with search |
| `sql/` | Standalone SQL queries for Part D |
| `.agent-context/` | Documentation for AI agents working on this project |

## Key Files

- `backend/laravel-api/app/Services/FeedRankingService.php` — Core ranking algorithm (all 4 signals + weights)
- `backend/laravel-api/app/Services/EmbeddingService.php` — Bridge to Python service with graceful NULL fallback
- `backend/laravel-api/app/Services/AuthenticityScoreService.php` — 7 sub-signals, 45% image / 55% text
- `backend/laravel-api/app/Models/Relationship.php` — Materialized directional relationship depth
- `backend/python-embedding/main.py` — FastAPI embedding endpoint
- `backend/python-embedding/embedding_service.py` — Model loading + deterministic mock fallback
- `mobile/GuisedUpFeed/src/screens/FeedScreen.js` — Main deliverable screen
- `mobile/GuisedUpFeed/src/styles/theme.js` — Brand colors and design tokens
- `sql/queries.sql` — All 4 SQL challenge queries (PostgreSQL syntax)
