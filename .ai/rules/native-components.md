---
paths:
  - 'app/NativeComponents/**'
  - 'app/NativeLayout/**'
---

# Native Components

## Shape
One class per screen, `final`, extending `NativeComponent` (or `NativeLayout`). Screens are flat in
`app/NativeComponents/`; shared behaviour goes in `app/NativeComponents/Concerns/`.

## Public properties are the state
Component state is public properties bound to `native:*` attributes (`native:model`, `native:key`,
`native:list`). Native attributes mutate them directly; do not add setters or mirror state into
private fields.

## Trait names are capability-style
`UsesAppMenu`, `UsesCaptcha`. A verb phrase like `TogglesPostReactions` names a behaviour rather than
a capability — prefer splitting that trait by capability instead of adding more.

## A trait must not hold state the consumer may not need
Traits carry methods, not component state. If several consumers receive properties they don't use,
that state belongs on the components that do.

## Every network call goes through `attempt()`
Call `$api->attempt(fn () => $api->someEndpoint())` and branch on `$response === null` **first**.
Never call an endpoint directly — `attempt()` is what converts a `ConnectionException` into a
handled path. On failure, reset any `busy` flag before returning, or the screen locks permanently.

## All 401 handling goes through `handleUnauthorized()`
Use the `UsesAppMenu` helper. Never hand-roll `TokenStorage::clear()` + `replace(login)`: that
skips `afterActiveAccountRemoved()`, so a multi-account session loses the active account and lands on
the login screen instead of switching to the next one.

## The four-step failure contract
Apply in this order at every call site: `$response === null` → `handleUnauthorized()` →
`! $response->successful()` → `ApiErrors::first()`.

## Permissions are the server's decision
Do not duplicate authorization logic. Hide the row when the client already knows the answer, and let
a `403` from the server be the final word. Do not cache "forbidden" per post beyond the current
screen.

## Constants, not magic numbers
Lengths, limits and durations are `public const int` on the component (e.g. `MAX_CONTENT_LENGTH`).
Do not repeat a limit inline in a blade or a second component — two components with the same limit
must not drift.

## `#[Poll]` is the views flush
Impression/view batching uses `#[Poll]` on the component. It must be paired with a `try`/`finally`
around anything that can throw, such as a navigation `replace()`.