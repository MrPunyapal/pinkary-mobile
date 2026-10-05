---
paths:
  - 'routes/*.php'
---

# Routes

## Screens are `Route::native()`
Declare each screen with `Route::native('/path', Screen::class)->name('mobile.name')`. The name is
required — components navigate by route name, never by path string.

## Keep `web.php` as one file
A single `routes/web.php` is correct while the app is one screen group. Split it only past roughly
150 lines or when a second route group appears, and split by screen area when you do.

## Never build a URL by hand
Use `route()` with a named route. For the API host, read `config('services.pinkary.url')` in the
service layer — routes never mention a domain.

## `routes/ai.php` is not shipped
MCP routes are stripped from release bundles by `config/nativephp.php`. Do not add application
behaviour that depends on them.