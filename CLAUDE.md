# CLAUDE.md

## Project: Guised Up — Real Connections Feed

This is a full-stack take-home assessment for Guised Up, a social platform. The project implements a personalized "Real Connections Feed" that ranks content by authenticity, relationship depth, semantic similarity, and time decay.

## Before You Start

**MANDATORY**: Read all files in `.agent-context/` before doing any work:

1. `.agent-context/ARCHITECTURE.md` — System design, request flows, key decisions
2. `.agent-context/STACK.md` — Technologies, versions, and why they were chosen
3. `.agent-context/STRUCTURE.md` — Repo layout and where everything lives
4. `.agent-context/CONVENTIONS.md` — Naming, style, and patterns to follow
5. `.agent-context/TESTING.md` — Test strategy, what to test, how to run tests

Also read `PLAN.md` for the execution timeline and phasing.

## Quick Reference

- **Backend**: Laravel 11 + PostgreSQL (pgvector) — lives in `backend/laravel-api/`
- **Embedding Service**: Python FastAPI with sentence-transformers — lives in `backend/python-embedding/`
- **Mobile**: React Native (Expo) — lives in `mobile/GuisedUpFeed/`
- **SQL Queries**: Raw SQL — lives in `sql/queries.sql`
- **Documentation**: Technical Solution Document — lives in `docs/TSD.md`

## Key Commands

```bash
# Laravel
cd backend/laravel-api
composer install
php artisan migrate --seed
php artisan serve
php artisan test

# Python embedding service
cd backend/python-embedding
pip install -r requirements.txt
uvicorn main:app --port 8001

# React Native
cd mobile/GuisedUpFeed
npm install
npx expo start
```

## Available Skills

- `/grill-with-docs` — Relentless interview to stress-test a plan or design, combined with domain modeling. Creates ADRs and a glossary (CONTEXT.md) as you go. Use before building to sharpen architecture decisions.

## Rules

- Follow conventions in `.agent-context/CONVENTIONS.md` strictly
- Business logic goes in `Services/`, not controllers
- All API responses use consistent JSON shape with `data` and `meta` keys
- Never commit `.env` files — use `.env.example`
- Mock the embedding service in tests (return fixed 384-dim vector)
- pgvector is required — SQLite won't work for vector operations
- Minimum 3 feature tests required (PostCreation, Feed, Search)
