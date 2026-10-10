# ADR-0001: Modular monolith with Clean Architecture layers

* Status: Accepted · Date: 2026-10-10

## Context
One agency, a small team, many features (auth, listings, MLS, CRM, scheduling, transactions,
documents). Microservices would multiply deployment, observability and consistency costs.

## Decision
A single Laravel API organized by module, with `Domain` / `Application` / `Infrastructure` / `Http`
layers. Contracts are introduced only where a swappable implementation exists (MLS providers,
storage, AI, AV scanning). Eloquent models stay in `app/Models`.

## Consequences
+ Simple deployment and transactions; clear seams for future extraction (MLS workers first).
− Discipline is required to keep module boundaries; enforced through review and folder structure.
