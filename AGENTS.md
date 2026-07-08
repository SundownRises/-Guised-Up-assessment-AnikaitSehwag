# AGENTS.md

## Instructions for All Agents

You are working on the Guised Up take-home assessment — a full-stack project with Laravel, Python, React Native, and PostgreSQL.

### Required Reading (Do This First)

Before performing ANY work on this codebase, read the following files in order:

1. **`.agent-context/ARCHITECTURE.md`** — Understand the system design, how components connect, and the request flows
2. **`.agent-context/STACK.md`** — Know the exact technologies, versions, and reasoning behind each choice
3. **`.agent-context/STRUCTURE.md`** — Know where every file lives and what each directory is for
4. **`.agent-context/CONVENTIONS.md`** — Follow these naming, style, and structural patterns exactly
5. **`.agent-context/TESTING.md`** — Understand what needs testing and how tests are structured

Do not skip this step. Do not assume you know the project structure. Read the files.

### After Reading Context

- Check `PLAN.md` for the overall execution plan and phase you're working in
- Check `docs/TSD.md` (if it exists) for architectural decisions already made
- Run `git status` to understand current state before making changes

### Agent Responsibilities by Area

#### Backend (Laravel) Agent
- Read: ARCHITECTURE, STACK, STRUCTURE, CONVENTIONS, TESTING
- Work in: `backend/laravel-api/`
- Key patterns: thin controllers, logic in Services/, Form Requests for validation, API Resources for responses
- Auth: Laravel Sanctum, all endpoints behind `auth:sanctum` middleware except login/register

#### Python Embedding Agent
- Read: ARCHITECTURE, STACK, STRUCTURE, CONVENTIONS
- Work in: `backend/python-embedding/`
- Key patterns: FastAPI with Pydantic models, type hints everywhere, sentence-transformers model
- The service is stateless — it receives text, returns a 384-dim float array

#### Mobile (React Native) Agent
- Read: ARCHITECTURE, STACK, STRUCTURE, CONVENTIONS
- Work in: `mobile/GuisedUpFeed/`
- Key patterns: functional components, StyleSheet.create(), all states handled (loading/empty/error), design tokens in theme.js
- No default RN styles — everything intentionally designed

#### SQL Agent
- Read: ARCHITECTURE, STRUCTURE, CONVENTIONS
- Work in: `sql/queries.sql`
- Key patterns: UPPERCASE keywords, one clause per line, meaningful aliases, comments only for non-obvious logic

#### Documentation Agent
- Read: All five .agent-context files + PLAN.md
- Work in: `docs/TSD.md`
- Must cover: architecture diagram, schema, vector DB choice, API design, ranking algorithm, AI tools used, trade-offs

### Available Skills

- **`/grill-with-docs`** — Use this to stress-test any plan or design before building. It runs a relentless interview session that walks through every decision branch, combined with domain modeling (glossary in `CONTEXT.md`, ADRs in `docs/adr/`). Invoke it when:
  - Starting a new architectural decision
  - Before implementing a major feature
  - When you need to sharpen fuzzy requirements

The skill is defined in `.claude/commands/grill-with-docs.md` and sources from `.agents/skills/grill-with-docs/`.

### Do NOT

- Create files outside the defined structure without asking
- Change the technology stack decisions (pgvector, Sanctum, Expo, FastAPI)
- Add dependencies that aren't justified by a requirement
- Write long comments or docblocks — code should be self-documenting
- Commit .env files or secrets
- Skip reading the .agent-context files
