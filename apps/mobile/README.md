# Hitlease Real Estate — Mobile (Flutter)

Android + iOS app. Flutter 3.47 (stable), Riverpod 3, GoRouter, Dio, Freezed +
json_serializable, flutter_secure_storage. English (default) and Spanish.

Authentication uses Sanctum **personal access tokens** (`POST /api/v1/auth/token`),
stored only in the platform keystore via `flutter_secure_storage`. A 401 from the API
clears the token and returns the user to sign-in.

```bash
flutter pub get
dart run build_runner build --delete-conflicting-outputs   # Freezed / JSON models
flutter analyze && flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000   # Android emulator → local API
```

`API_BASE_URL` is a build-time define (no secrets). Debug builds allow HTTP for local
development; release builds require HTTPS.

Structure: `lib/core` (config, network, storage, router, theme, l10n),
`lib/features/<feature>/{data,domain,application,presentation}`, `lib/shared/widgets`.

Pending (see `docs/product-backlog.md`): Firebase Cloud Messaging (requires the
agency's Firebase project files), Mapbox SDK (requires a Mapbox access token),
property search/map/favorites/messages (depend on API modules of later sprints),
agent-mode screens.
