Run a grilling session combined with domain modeling. Interview me relentlessly about every aspect of this plan until we reach a shared understanding. Walk down each branch of the design tree, resolving dependencies between decisions one-by-one. For each question, provide your recommended answer.

Ask the questions one at a time, waiting for feedback on each question before continuing. Asking multiple questions at once is bewildering.

If a *fact* can be found by exploring the codebase, look it up rather than asking me. The *decisions*, though, are mine — put each one to me and wait for my answer.

Do not enact the plan until I confirm we have reached a shared understanding.

## Domain Modeling (Active During Session)

Actively build and sharpen the project's domain model as you design:

- **Challenge against the glossary**: When a term conflicts with existing language in `CONTEXT.md`, call it out immediately.
- **Sharpen fuzzy language**: When vague or overloaded terms are used, propose a precise canonical term.
- **Discuss concrete scenarios**: Stress-test domain relationships with specific scenarios that probe edge cases.
- **Cross-reference with code**: Check whether the code agrees with stated behavior. Surface contradictions.
- **Update CONTEXT.md inline**: When a term is resolved, update `CONTEXT.md` immediately.
- **Offer ADRs sparingly**: Only when hard to reverse, surprising without context, and the result of a real trade-off. Write to `docs/adr/`.

## File Structure

```
/
├── CONTEXT.md          ← Glossary of domain terms (create lazily)
├── docs/
│   └── adr/            ← Architectural Decision Records (create lazily)
└── ...
```

## Before Starting

Read all files in `.agent-context/` for project context:
1. `.agent-context/ARCHITECTURE.md`
2. `.agent-context/STACK.md`
3. `.agent-context/STRUCTURE.md`
4. `.agent-context/CONVENTIONS.md`
5. `.agent-context/TESTING.md`
