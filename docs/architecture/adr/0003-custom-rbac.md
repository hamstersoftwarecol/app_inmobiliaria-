# ADR-0003: In-house RBAC tables instead of a third-party package

* Status: Accepted · Date: 2026-10-10

## Context
The prompt requires `roles`, `permissions`, `role_user`, `permission_role` tables, granular
permissions, and a hierarchy to stop privilege escalation. Popular packages use different table
names and do not model hierarchy.

## Decision
Small in-house implementation: `Permission` and `RoleName` enums are the source of truth, an
idempotent seeder syncs them, one Gate per permission, Policies combine permissions with ownership
and role level, super admin bypass via `Gate::before`.

## Consequences
+ Exact schema requested, no extra dependency, typed permissions in code.
− We own caching/edge cases (per-request permission cache in `User`).
