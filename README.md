# Guised Up — Real Connections Feed

Full-stack assessment: a personalized social feed that ranks content by **authenticity**, **relationship depth**, **semantic similarity**, and **time decay** — not engagement metrics.

## Project Structure

```
├── docs/TSD.md                    # Technical Solution Document
├── backend/laravel-api/           # Laravel 11 API (PHP 8.2)
├── backend/python-embedding/      # FastAPI embedding service (sentence-transformers)
├── mobile/GuisedUpFeed/           # React Native (Expo) feed screen
├── sql/queries.sql                # Raw SQL challenge queries (D1–D4)
└── docker-compose.yml             # One-command full stack setup
```

## Prerequisites (New Machine Setup)

You need these installed before anything else:

| Tool | Version | Download |
|------|---------|----------|
| **Docker Desktop** | Latest | https://www.docker.com/products/docker-desktop/ |
| **Node.js** | 18+ | https://nodejs.org/ |
| **Git** | Latest | https://git-scm.com/downloads |

Docker handles PostgreSQL, the embedding service, and the Laravel API — no need to install PHP, Python, Composer, or PostgreSQL locally.

## Quick Start (Docker — recommended)

### 1. Clone and start the backend stack

```bash
git clone <repo-url> GuisedUp
cd GuisedUp
docker compose up --build
```

Wait until all three services are healthy (first run takes a few minutes to download the ML model):
- **PostgreSQL 16** with pgvector extension on port `5432`
- **Python embedding service** (all-MiniLM-L6-v2) on port `8001` — first start downloads ~90MB model
- **Laravel API** on port `8000`

The API container automatically runs migrations and seeds on startup. Check logs to confirm:

```bash
docker compose logs api
```

Look for the two Bearer tokens printed by the seeder:

```
Anika's token: 1|abc123...
Ravi's token:  2|def456...
```

### 2. Set up the React Native mobile app

```bash
cd mobile/GuisedUpFeed
npm install
```

Before running, update `src/services/api.js`:
- Replace `AUTH_TOKEN` with Anika's token from the seeder output
- For web testing: the default `http://localhost:8000/api` works as-is
- For device testing: replace `192.168.1.2` with your machine's local IP (`ipconfig` on Windows, `ifconfig` on Mac/Linux)

### 3. Run the mobile app

```bash
npx expo start
```

Press `w` to open in browser, or scan the QR code with Expo Go on your phone.

### Stopping and restarting

```bash
# Stop all services (data persists in Docker volume)
docker compose down

# Restart (no rebuild needed unless you change backend code)
docker compose up -d

# If you changed backend code, rebuild:
docker compose up -d --build api
```

### Troubleshooting

| Issue | Fix |
|-------|-----|
| Port 5432 already in use | Stop any local PostgreSQL service, or another Docker container using that port |
| Port 8000 already in use | Stop any local `php artisan serve` process — the Docker container serves the API |
| Embedding service unhealthy | First start takes ~2 min to download the model. Check: `docker compose logs embedding` |
| 500 error on API calls | Check `docker compose logs api` for the stack trace |
| Mobile app can't reach API | Ensure Docker is running and port 8000 is accessible. On Windows, check firewall settings |

## Manual Setup (without Docker)

> Only use this if you cannot run Docker. The Docker path above is simpler and ensures consistent environments.

### Prerequisites

- PHP 8.2+ with Composer
- PostgreSQL 16+ with pgvector extension installed
- Python 3.10+ with pip
- Node.js 18+ with npm

### 1. PostgreSQL

Install PostgreSQL and the pgvector extension for your platform:
- **Windows**: Download from https://www.postgresql.org/download/windows/ — then install pgvector separately (https://github.com/pgvector/pgvector#windows)
- **macOS**: `brew install postgresql@16 pgvector`
- **Linux**: `sudo apt install postgresql-16 postgresql-16-pgvector`

Start PostgreSQL, create the database, and enable pgvector:

```sql
CREATE DATABASE guisedup;
\c guisedup
CREATE EXTENSION IF NOT EXISTS vector;
```

### 2. Python Embedding Service

```bash
cd backend/python-embedding
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8001
```

Verify: `curl http://localhost:8001/health`

### 3. Laravel API

```bash
cd backend/laravel-api
composer install
cp .env.example .env
# Edit .env — set DB_PASSWORD and ensure EMBEDDING_SERVICE_URL=http://localhost:8001
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The seeder prints two Bearer tokens. Example output:

```
Anika's token: 1|abc123...
Ravi's token:  2|def456...
```

### 4. React Native (Expo)

```bash
cd mobile/GuisedUpFeed
npm install
npx expo start
```

Before running, update the token and IP in `src/services/api.js`:
- Replace `AUTH_TOKEN` with Anika's token from the seeder
- Replace the IP address (`192.168.1.2`) with your machine's local IP (for device testing)

## Environment Variables

See `backend/laravel-api/.env.example`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=guisedup
DB_USERNAME=postgres
DB_PASSWORD=

EMBEDDING_SERVICE_URL=http://localhost:8001
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/posts` | Create a post (auto-generates embedding + authenticity score) |
| GET | `/api/feed` | Personalized ranked feed (paginated, 20/page) |
| GET | `/api/search?q={query}` | Semantic search via vector similarity (top 10) |
| POST | `/api/interactions` | Log interaction (view, reaction, reply) |

All endpoints require `Authorization: Bearer <token>` header.

## Running Tests

```bash
cd backend/laravel-api
php artisan test
```

Three feature tests cover:
- **PostCreationTest** — happy path, auth, validation
- **FeedTest** — pagination, ranking, auth
- **SearchTest** — semantic results, empty, validation

Tests mock the embedding service and use `RefreshDatabase`.

## Feed Ranking Algorithm

```
score = (0.25 x authenticity) + (0.35 x relationship_depth) + (0.25 x semantic_similarity) - (0.15 x time_decay)
```

- **Authenticity** — computed at post creation from text heuristics (length, hashtag density, caps ratio, URL count)
- **Relationship depth** — directional score materialized from interaction history (view=1, reaction=2, reply=3)
- **Semantic similarity** — cosine distance between post embedding and user interest vector (384-dim, EMA-updated)
- **Time decay** — exponential: `1 - e^(-0.02 * age_hours)`

## Technical Decisions

| Choice | Why |
|--------|-----|
| pgvector (not Pinecone/Qdrant) | Single database, zero extra infra, sufficient at MVP scale |
| all-MiniLM-L6-v2 | Free, local, fast (~10ms), 384-dim is compact |
| Synchronous embedding | <50ms latency avoids queue infrastructure; NULL fallback if service is down |
| All posts as candidates | Avoids cold-start; ranking algorithm filters instead of social graph |
| Offset pagination | Matches spec requirements; cursor-based is a future optimization |

## AI Tools Used

- **Claude Code (Claude Opus)** — architecture design, TSD writing, full implementation across all four parts, code review
- **Workflow**: Grilling session to stress-test design decisions before coding, then systematic implementation phase-by-phase

See the "AI Tool Usage" section in [docs/TSD.md](docs/TSD.md) for detailed breakdown.

## What's Not Included (Out of Scope)

- User registration/login UI (hardcoded token)
- Image upload (accepts URL only)
- Real image filter/retouching detection (placeholder scoring)
- Redis caching, rate limiting, push notifications
- Reply UI (interactions seeded in DB only)
