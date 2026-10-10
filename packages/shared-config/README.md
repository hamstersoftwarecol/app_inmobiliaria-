# @hitlease/shared-config

Shared conventions across apps:

* Brand tokens: primary `#123B5D`, secondary `#0F766E`, background `#FFFFFF`, font Inter
  (`apps/web/src/app/globals.css`, `apps/mobile/lib/core/theme/app_theme.dart`).
* Languages: English (default), Spanish.
* Currency USD; agency time zone `America/New_York`; timestamps exchanged as ISO-8601 UTC.
* Error envelope and codes: see `docs/api/openapi.yaml#/components/schemas/Error`.

Lint/format configs currently live in each app (Pint, ESLint, `analysis_options.yaml`); they will
move here if a second package needs them.
