# Coding Conventions

## General

- Write clear, readable code over clever code
- No unnecessary comments — code should be self-documenting via naming
- Keep functions/methods short and single-purpose
- Fail fast — validate inputs early, return early on errors

## PHP / Laravel

### Naming
- Controllers: `PascalCase` + `Controller` suffix (e.g., `FeedController`)
- Models: singular `PascalCase` (e.g., `Post`, `Interaction`, `Relationship`)
- Migrations: snake_case with numeric prefix (e.g., `0001_enable_pgvector`, `0002_create_users_table`)
- Routes: kebab-case URLs, resource naming (e.g., `/api/posts`, `/api/feed`)
- Variables/methods: `camelCase`
- Database columns: `snake_case`
- Services: `PascalCase` + `Service` suffix (e.g., `FeedRankingService`)

### Structure
- Business logic lives in `Services/`, not controllers
- Controllers are thin — validate, delegate to service, return resource
- Use Form Requests for validation (`CreatePostRequest`, `LogInteractionRequest`)
- Use API Resources for response shaping (`PostResource`)
- Use Eloquent relationships, not raw joins (except in the SQL challenge)

### Database
- Always use migrations — no manual schema changes
- Add indexes for columns used in WHERE, ORDER BY, JOIN
- Use foreign key constraints
- Soft deletes only where business logic requires it (not by default — we don't use them here)
- Vector columns use `vector(384)` type via pgvector

### Auth
- Laravel Sanctum with `auth:sanctum` middleware on protected routes
- Token returned on seeding, sent as `Authorization: Bearer {token}`
- All 4 API endpoints require authentication

### API Response Shape
All successful responses:
```json
{
  "data": { ... },
  "meta": { "page": 1, "per_page": 20, "total": 47 }
}
```

Error responses:
```json
{
  "message": "Human-readable error description",
  "errors": { "field_name": ["Specific validation error"] }
}
```

Status codes: 200 (OK), 201 (Created), 401 (Unauthorized), 422 (Validation Error), 500 (Server Error)

### Specific API Behaviors
- `POST /api/posts` returns 201 with `"embedding_status": "generated"` or `"pending"` (if Python service was down)
- `GET /api/feed` returns offset pagination: `?page=1&per_page=20`, meta has `page`, `per_page`, `total`
- `GET /api/search` returns max 10 results with `"meta": { "count": N, "threshold": 0.2 }`
- `POST /api/interactions` with `type: 'reaction'` is idempotent — second call removes it (toggle), returns `{ "data": { "action": "removed" } }`
- Views are NOT deduplicated (multiple views are valid)

## Python / FastAPI

### Naming
- Files: `snake_case.py`
- Functions: `snake_case`
- Classes: `PascalCase`
- Constants: `UPPER_SNAKE_CASE`

### Structure
- `main.py` — FastAPI app, route definitions, Pydantic models
- `embedding_service.py` — Model loading, inference, and deterministic mock fallback
- Type hints on all function signatures
- Pydantic models for request/response validation

### Embedding Service Behavior
- `POST /embed` accepts `{ "text": "..." }`, returns `{ "embedding": [float, ...], "dimensions": 384, "model": "all-MiniLM-L6-v2" }`
- If model can't be loaded: return deterministic mock embedding based on text hash (384-dim)
- Model: `sentence-transformers/all-MiniLM-L6-v2`

### Dependencies
- Pin versions in `requirements.txt`
- Use virtual environments (venv)

## React Native / JavaScript

### Naming
- Components: `PascalCase` (e.g., `PostCard.js`)
- Hooks/utilities: `camelCase` (e.g., `useFeed.js`)
- Styles: `camelCase` property names in StyleSheet
- Constants: `UPPER_SNAKE_CASE`

### Structure
- One component per file
- Screens in `screens/`, reusable UI in `components/`
- API calls isolated in `services/api.js`
- Styles co-located in the component file OR in a shared `theme.js` for global tokens

### Patterns
- Functional components only (no class components)
- Use `useState`, `useEffect`, `useCallback` hooks
- Destructure props at the function signature
- Handle all async states: loading, success, error, empty

### Styling
- Use `StyleSheet.create()` — no inline style objects
- Design tokens (colors, spacing, fonts) in `theme.js`
- No default React Native styles — everything must be intentionally styled
- Consistent spacing scale (4, 8, 12, 16, 24, 32)

### Brand Color Palette
```javascript
const COLORS = {
  primary: '#E8613C',      // terracotta orange — headers, active states, reaction button
  background: '#FDF8F4',   // warm off-white — screen background
  card: '#FFFFFF',         // post cards
  textPrimary: '#2D2D2D', // usernames, post text
  textSecondary: '#8C8C8C', // timestamps, metadata
  border: '#F0E8E2',      // card borders, separators
  accent: '#D4A574',      // warm tan — avatar placeholder rings
};
```

### Design Details
- Rounded cards: borderRadius 16
- Card gaps: 16px
- Circular avatar placeholders with warm-tone (accent) borders
- Subtle card shadows (not flat, not Material-heavy)
- Heart button: outlined → filled terracotta on tap

### Mobile-Specific Behaviors
- Heart button toggles reaction (POST /api/interactions type='reaction')
- Views auto-logged via FlatList `onViewableItemsChanged` (POST /api/interactions type='view')
- Search bar: debounced input, swaps feed data for search results when query is active
- Infinite scroll via FlatList `onEndReached`
- Pull-to-refresh via RefreshControl
- Hardcoded Bearer token from seeder (no login UI)

## SQL

- Keywords: UPPERCASE (`SELECT`, `FROM`, `WHERE`, `JOIN`)
- Table/column names: lowercase snake_case
- Use aliases for readability in complex queries
- One clause per line for readability
- Add comments only for non-obvious logic
- **PostgreSQL syntax** (not ANSI-generic) — use `NOW() - INTERVAL '7 days'`, etc.
- D1 counts interactions equally (view/reply/reaction each = 1 count) — NOT weighted

## Git

- Commit messages: imperative mood, present tense ("Add feed endpoint", not "Added")
- Branch naming: `feature/`, `fix/`, `docs/` prefixes
- Keep commits atomic — one logical change per commit
- Include `.env.example` but never `.env`
