---
paths:
  - 'app/Support/**'
---

# Support

## Stateless helpers are static classes
`app/Support/` holds `final` classes with only `public static` methods. No constructor, no injected
state, no instance methods.

## If it needs state, it is not a Support class
Anything with lifecycle, caching or per-request state belongs on a component or in a service.
Support is for pure helpers.

## Naming
Name the class after the concept, not the mechanism — `ApiErrors`, `FeedCache`, `UnreadCount`.

## Swallowed exceptions need a reason
`catch (Throwable)` that returns a fallback is only acceptable where degrading is correct (a badge
count, a cached value). Anything that silently discards a failure the user needs to know about must
surface through the normal error contract.

## Document array shapes once, at the shape
If a payload shape appears in more than one method, declare it as a typed `array{...}` return or
`@param` on the method that produces it rather than repeating a looser shape at each consumer.

## Never pass a typed array to `data_set()`
`data_set()` is declared `mixed &$target`, so handing it an `array<string, mixed>` variable widens
that variable to `mixed` and every later use of it fails static analysis. Assign the nested offset
directly (`$post['thread'] = $mirror`) or write a small typed helper. `Arr::set()` is better but
still takes `array &$array`, so re-narrow with `array_filter(..., ARRAY_FILTER_USE_KEY)` if a
`list<>` or `array<string, mixed>` contract is required.

Reading several fields off one `Response::json()` result means several `Payload::` calls. A
`@var array<string, mixed>` on the row narrows every read at once and is the exception to the usual
no-inline-`@var` guidance — see `payloadRow()`.

## Native cache and storage defaults are not the test defaults
Helpers that cache write to a real disk or store in production. Tests must override the store
explicitly; do not rely on a default being harmless.