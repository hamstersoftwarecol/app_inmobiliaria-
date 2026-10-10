# @hitlease/api-contracts

Single source of truth for the API contract: [`docs/api/openapi.yaml`](../../docs/api/openapi.yaml)
(OpenAPI 3.1, linted in CI with Redocly).

Consumers today:
* Web: hand-written types in `apps/web/src/types/api.ts`.
* Mobile: Freezed models in `apps/mobile/lib/features/*/domain`.

Planned (Sprint 4, when the property/search contract grows): generate TypeScript types with
`openapi-typescript` into this package and Dart models with `openapi-generator`, and fail CI when
generated code drifts from the spec.
