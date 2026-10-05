---
paths:
  - 'tests/**'
---

# Tests

## `it()` only
Every test is a `it('lowercase sentence')` closure. Do not use `test()`, PHPUnit classes, or
`describe()` blocks, and do not mix styles in one file.

## Extend `Tests\TestCase`
Feature tests get isolation from `tests/TestCase.php`. Extending `PHPUnit\Framework\TestCase`
directly silently loses `Http::preventStrayRequests()`, `Storage::fake()` and `FakeBridge::enable()` —
which lets a test write real token files to the local disk. Never do it.

## No mocking/test doubles
Test against real collaborators rather than `mock()`/`spy()`/Mockery. Fake HTTP at the boundary with
`Http::fake()` and assert on the request that was sent.

## Shared setup belongs in `tests/Pest.php`
The API host, the profile fixtures and any other repeated preamble are defined once there. Do not
repeat `config(['services.pinkary.url' => ...])` per file — `Http::assertSent()` compares strings
exactly, so one divergent copy silently stops matching.

## Assert classes individually, never as an ordered string
Class order is normalised by Prettier. `toContain('text-sm font-semibold text-red-500')` breaks the
moment a class is added or reordered. Assert each class separately, or against a normalised copy.

## Cast values from the layout engine before comparing
`Native::test()->tree()` returns layout dimensions that are numeric but not `int`. Compare with an
explicit cast — `(float) $node['layout']['width'] === 40.0` — never a loose `==`, and never a bare
`===` against an uncast value, which will match nothing and read as "0 elements found".

## Control the clock for time-dependent assertions
`freezeTime()`/`travel()` for anything involving TTLs, relative timestamps or dedupe windows. An
assertion on a relative-time string that only passes because the fixture hardcodes `"2 years ago"` is
testing the fixture, not the formatter.

## Be parallel-safe
Use `Str::uuid()` in temp paths and delete them in a `finally`. Never a fixed filename plus `uniqid()`,
and never leave a file behind. Prefer a scoped `Cache::store()` override over `cache:clear`.

## Coverage is not a gate yet
`pest --coverage` is available but no threshold is enforced. Read the report to find gaps; do not
assume a low number is acceptable.