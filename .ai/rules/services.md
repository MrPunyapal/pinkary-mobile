---
paths:
  - 'app/Services/**'
---

# Services

## `PinkaryApi` is the only HTTP surface
Every call to the Pinkary API goes through `app/Services/PinkaryApi.php`. Do not add a second client,
and do not call `Http::` directly from a component.

## One thin method per endpoint
A method takes typed arguments, sends one request, and returns the `Illuminate\Http\Client\Response`.
No branching, no response inspection, no error handling — that belongs to the caller. If a method
needs an `if`, the logic is in the wrong layer.

## `attempt()` lives here and is not reimplemented
`attempt()` wraps a closure and converts `ConnectionException` to `null`. It is the only sanctioned
retry/transport policy in the app. Do not add a second variant.

## Response normalisation is not an endpoint concern
`normalizeQuestion()`/`normalizeQuestions()` shape server payloads into the arrays components read.
Keep them pure — no HTTP, no config, no side effects — and keep them adjacent so a payload change is
a one-file edit.

## Do not mix transport policy into the endpoint list
A method that is not an endpoint (a normaliser, a transport helper) is a smell that the class is doing
two jobs. Split before the second non-endpoint method lands.

## Token handling
Auth state lives in `app/Support/AccountStore`, encrypted in SecureStorage. `TokenStorage` is a
container-resolvable wrapper over it — if you need to change how a token is read, written or cleared,
change `AccountStore`, not the wrapper.