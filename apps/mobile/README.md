# App Inmobiliaria Real Estate — Mobile (Flutter)

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

Screens: search and listing detail, favorites, saved searches, showings, notifications,
messages, transactions, offers (record, counter, accept, reject, withdraw), agent directory and
profiles, account (profile, password, two-factor authentication, signed-in devices, e-mail
preferences, account closure) and, for agents, leads, tasks, availability/time off and their own
agent profile and licences.

Web-only on purpose: administration (users, roles, agency, licence review, MLS, reports), listing
management with photo uploads, transaction document uploads and e-mail address changes.

Pending (see `docs/product-backlog.md`): Firebase Cloud Messaging (requires the
agency's Firebase project files) and the map tab (requires a Mapbox access token).

## Release

**Icons and splash.** `assets/branding/` holds placeholder artwork in the brand colours; replace
the PNGs with the final logo and run `dart run flutter_launcher_icons` and
`dart run flutter_native_splash:create`.

**Android signing.** Create an upload key once (`keytool -genkey -v -keystore upload.jks
-keyalg RSA -keysize 2048 -validity 10000 -alias upload`) and keep it out of git. Locally, put
`storeFile`, `storePassword`, `keyAlias` and `keyPassword` in `android/key.properties`. In GitHub,
add the secrets `ANDROID_UPLOAD_KEYSTORE_BASE64` (`base64 -w0 upload.jks`),
`ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS` and `ANDROID_KEY_PASSWORD` to the `production`
environment and run the **Mobile release build** workflow; it uploads the signed `.aab`. Without
a key, release builds fall back to the debug key and must not be uploaded.

**iOS.** Set the team and signing in Xcode (Runner target) and archive from Xcode or
`flutter build ipa`. The App ID needs the *Associated Domains* capability for Universal Links.

**Links from e-mails (App Links / Universal Links).** Build with the website host:
`-PappLinkHost=www.example.com` (or `APP_LINK_HOST`) on Android and `APP_LINK_HOST` in
`ios/Flutter/Release.xcconfig`. The website must serve the verification files: set
`ANDROID_APP_CERT_SHA256` (the Play app-signing certificate fingerprint) and `APPLE_TEAM_ID` on the
web app; `/.well-known/assetlinks.json` and `/.well-known/apple-app-site-association` return 404
until they are set. Links for password reset, e-mail verification, unsubscribe, listings and
`/dashboard/…` pages then open the matching screen; protected ones reopen after sign-in.
