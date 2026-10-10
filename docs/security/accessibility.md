# Accessibility (WCAG 2.1 AA target)

## Automated (every CI run)

`apps/web/tests/e2e/accessibility.spec.ts` runs axe-core with the `wcag2a`, `wcag2aa`, `wcag21a` and
`wcag21aa` rule sets and fails on any *serious* or *critical* violation on:

* public pages — home, search results, listing detail, agent directory, agent profile, sign-in,
  registration (desktop and mobile viewports);
* buyer pages — dashboard, favorites, showings, messages, notifications, settings;
* staff pages — admin dashboard, users, audit log, CRM, listings, offers, transactions.

First run (2026-10-10): no serious or critical violations.

## Built in

Semantic landmarks and a skip link; every form control has a visible label, errors are announced
(`role="alert"`) and linked with `aria-describedby`; buttons have accessible names (icons are
`aria-hidden`); focus is visible; dialogs and drag-and-drop have keyboard alternatives (CRM "move
to" control); charts are keyboard-readable (focus + arrow keys) with a table view and never rely on
colour alone; text contrast meets AA with the brand tokens; EN/ES with `lang` on Spanish text.
The Flutter app uses Material semantics (labels, tooltips on icon buttons) and system text scaling.

## Manual checks before each release (automation covers roughly a third of issues)

1. Keyboard only: sign in (incl. MFA), search, open a listing, request a showing, send a message,
   move a lead, upload a document. No traps; logical order; visible focus.
2. Screen reader (NVDA + Firefox, VoiceOver + Safari, TalkBack on Android): the same flows.
3. 200 % zoom and 320 px width: no horizontal scrolling or clipped text.
4. Reduced motion and forced colours (Windows high contrast).

Not certified: this is a self-assessment, not a third-party conformance audit.
