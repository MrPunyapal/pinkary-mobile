# Pinkary Mobile

Pinkary in your pocket — an official Android app for [Pinkary](https://pinkary.com), built with Laravel and [NativePHP](https://nativephp.com). The screens are written in Blade and rendered as real native UI (Jetpack Compose on Android, SwiftUI on iOS).

## Status

- Android development builds work on GitHub Actions.
- An iOS build workflow and an iOS simulator check are configured; the app is Android-first.

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

The first GitHub-hosted build takes ~9 minutes (Gradle downloads and compiles the NativePHP Android toolchain); with the runner cache warm, ~5-6 minutes.

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

- `app/NativeComponents/` — one class per screen. Twelve screens: `Bookmarks`, `Compose`, `Detail`, `EditProfile`, `FollowList`, `Home` (feed), `Login`, `Notifications`, `Profile`, `Register`, `Search`, `Settings`. Each extends `Native\Mobile\Edge\NativeComponent`, holds screen state, calls the API, and renders a Blade view. Shared behaviour lives in `app/NativeComponents/Concerns/` (`UsesAppMenu`, `UsesCaptcha`, `TogglesPostReactions`).
- `resources/views/native/*.blade.php` — the screen markup, written with `<native:*>` components from `nativephp/mobile-ui`. Visual styling is theme-driven: colors, radii, and typography come from `config/native-ui.php`, not per-element hex values.
- `resources/views/components/` — shared pieces (34 of them, including `post`, `post-text`, `post-images`, `post-poll`, `user-row`, `avatar`, and the `skeleton-*` loading placeholders).
- `app/Services/PinkaryApi.php` — thin HTTP client for `PINKARY_API_URL` (`/api/v1/...` endpoints on the Pinkary web app). An unauthorized response drops the dead account and, if none remain, returns the user to login.
- `app/Support/AccountStore.php` — holds the API tokens. They are kept in the **native secure storage** (Android Keystore / iOS Keychain) because web sessions are not durable on mobile; a Laravel session value is only used to migrate a legacy account in.
- `routes/web.php` — `Route::native(...)` registrations for the screens.
- `tests/` — `Feature/` (in-process NativePHP component tests, no device needed), `Unit/` (helpers in isolation), and `Arch/` (architecture rules). Run the whole gate with `composer test`, which chains Pint, PHPStan, type coverage, line coverage and the suite.

## API endpoints used

All require a Sanctum token from `POST /api/v1/auth/login` (or `.../auth/register`):

| Endpoint | Purpose |
| --- | --- |
| `POST /api/v1/auth/login` | Sign in, including the two-factor challenge step |
| `POST /api/v1/auth/register` | Create an account |
| `POST /api/v1/auth/logout` | Revoke the current token |
| `GET /api/v1/captcha` | Turnstile challenge for registration |
| `GET /api/v1/feed` | Answered feed (`?tab=recent`, `following`, or `trending`) |
| `GET /api/v1/profile` | The authenticated user's profile |
| `PATCH /api/v1/profile` | Update the profile, including the link shape |
| `GET /api/v1/search` | Users and hashtags |
| `GET /api/v1/users/{username}` | Another user's profile |
| `POST`/`DELETE /api/v1/users/{username}/follow` | Follow and unfollow |
| `GET /api/v1/users/{username}/questions` | A user's posts |
| `GET /api/v1/users/{username}/followers` | Follower and following lists |
| `GET /api/v1/questions` | Create a post |
| `GET /api/v1/questions/{id}/comments` | Comments on a post |
| `POST /api/v1/questions/{id}/comments` | Add a comment |
| `POST /api/v1/questions/views` | Batch impression reporting |
| `POST /api/v1/images` | Upload post images |
| `GET /api/v1/notifications` | Notifications and the unread count |
| `POST /api/v1/notifications/read` | Mark notifications read |
| `GET /api/v1/bookmarks` | Bookmarked posts |
| `GET`/`POST /api/v1/links` | List and create profile links |
| `PUT`/`DELETE /api/v1/links/{id}` | Edit and remove a profile link |
| `GET /api/v1/channels` | Channel picker for a new post |

`app/Services/PinkaryApi.php` is the single place these are called from. The server-side implementation lives in the Pinkary web repository (`app/Http/Controllers/Api/*`, `app/Http/Requests/Api/*`).

## Repository map

- Mobile app: `pinkary-project/pinkary-mobile`, branch `main`
- Reusable build action: `MrPunyapal/nativephp-build-action`, branch `main`, pinned to commit `18e8bc2bc9b2189a2a0d727a43048895472487b5` (installs the Android SDK without the obsolete `tools` package and supports projects without `package-lock.json`)

## License

MIT © Bifrost Technologies LLC. See [LICENSE](LICENSE) and [CONTRIBUTING](CONTRIBUTING.md).
