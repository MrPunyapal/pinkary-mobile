# Pinkary Mobile

Pinkary in your pocket — an official Android app for [Pinkary](https://pinkary.com), built with Laravel and [NativePHP](https://nativephp.com). The screens are written in Blade and rendered as real native UI (Jetpack Compose on Android, SwiftUI on iOS).

## Status

- Android development builds work on GitHub Actions.
- The iOS side is not configured yet.

## Build an APK

1. Open [Build Android](https://github.com/pinkary-project/pinkary-mobile/actions/workflows/build-android.yml).
2. Choose **Run workflow**, set `build-type: debug` (default), and run.
3. Download the `pinkary-android-debug` artifact ZIP, extract the APK, and install it on a device.

Debug builds generate a temporary keystore on the runner — for development/testing only, never for a store release. Release and bundle builds require these GitHub Secrets:

| Secret | Purpose |
| --- | --- |
| `ANDROID_KEYSTORE_BASE64` | Upload signing keystore (base64) |
| `ANDROID_KEYSTORE_PASSWORD` | Keystore password |
| `ANDROID_KEY_ALIAS` | Key alias |
| `ANDROID_KEY_PASSWORD` | Key password |

The first GitHub-hosted build takes ~9 minutes (Gradle downloads and compiles the NativePHP Android toolchain); later builds are faster with runner caches.

## Requirements

- PHP 8.4+ and Composer
- Node (for Vite assets)
- Android Studio (for native runs/builds) — see the [NativePHP Mobile v4 environment guide](https://nativephp.com/docs/mobile/4/getting-started/environment)

## Local development

```bash
composer install
npm install
npm run build
php artisan migrate
php artisan native:install --no-interaction
```

Add these to the mobile `.env` (see `.env.example`):

```env
PINKARY_API_URL=https://pinkary.com
NATIVEPHP_RUNNING=true
```

Run the app:

```bash
php artisan native:run
```

Notes:

- The generated `storage/framework/native_routes.json` is local runtime output — keep it uncommitted.
- The mobile bundle keeps `routes/api.php`, which Laravel requires during bootstrap.
- Release and bundle builds are signed via the GitHub Secrets listed above; debug builds sign with a temporary keystore.

## Architecture

- `app/NativeComponents/` — one class per screen (`Welcome`, `Login`, `Register`, `Home` (feed), `Profile`). Each extends `Native\Mobile\Edge\NativeComponent`, holds screen state, calls the API, and renders a Blade view.
- `resources/views/native/*.blade.php` — the screen markup, written with `<native:*>` components from `nativephp/mobile-ui`. Visual styling is theme-driven: colors, radii, and typography come from `config/native-ui.php`, not per-element hex values.
- `resources/views/components/` — shared pieces (`post`, `post-author`, `pinkary-logo`).
- `app/Services/PinkaryApi.php` — thin HTTP client for `PINKARY_API_URL` (`/api/v1/...` endpoints on the Pinkary web app). The token is stored in the session; an unauthorized feed response logs the user out.
- `routes/web.php` — `Route::native(...)` registrations for the five screens.
- `tests/Feature/*Test.php` — in-process NativePHP tests (no device needed). Run with `php artisan test`.

## API endpoints used

All require a Sanctum token from `POST /api/v1/auth/login` (or `.../auth/register`):

| Endpoint | Purpose |
| --- | --- |
| `GET /api/v1/feed` | Answered feed for the authenticated user (`?tab=recent`, `following`, or `trending`) |
| `GET /api/v1/profile` | Authenticated user's profile |
| `POST /api/v1/auth/logout` | Revoke the current token |

The server-side implementation lives in the Pinkary web repository (`API` branch at the time of writing, not pushed) — `app/Http/Controllers/Api/*`, `app/Http/Resources/QuestionResource.php`.

## Repository map

- Mobile app: `pinkary-project/pinkary-mobile`, branch `main`
- Reusable build action: `MrPunyapal/nativephp-build-action`, branch `main`, pinned to commit `18e8bc2bc9b2189a2a0d727a43048895472487b5` (installs the Android SDK without the obsolete `tools` package and supports projects without `package-lock.json`)

## License

MIT © Bifrost Technologies LLC. See [LICENSE](LICENSE) and [CONTRIBUTING](CONTRIBUTING.md).
