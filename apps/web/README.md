# App Inmobiliaria Real Estate — Web (Next.js)

Next.js 15 (App Router) + TypeScript + Tailwind CSS 4 + shadcn/ui-style components,
TanStack Query, Zustand, React Hook Form + Zod. English (default) and Spanish.

All business data comes from the Laravel API (`NEXT_PUBLIC_API_URL`). Authentication
uses Sanctum **cookie sessions + CSRF** — no tokens are stored in browser storage.

```bash
cp .env.example .env.local
npm ci
npm run dev            # http://localhost:3000 (API must run on :8000)
npm run lint && npm run typecheck
npm test               # Vitest unit/component tests
npm run build && npm run test:e2e   # Playwright (needs API + DB running)
```

Structure: `src/app` (routes), `src/features/<feature>/components`, `src/components/{ui,layout}`,
`src/services` (API calls), `src/hooks`, `src/stores` (Zustand), `src/validations` (Zod),
`src/lib` (api client, i18n, utils), `src/types`.
