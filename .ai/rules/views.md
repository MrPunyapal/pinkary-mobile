---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Anonymous components for UI
Build reusable pieces as anonymous Blade components in `resources/views/components/` with `@props`.
Create a class-backed component only when it needs constructor or render PHP.

## Native elements, not HTML
Use `native:*` elements for anything the renderer draws. Plain `<div>`/`<span>`/`<img>`/`<ul>` do not
lay out natively. `native:text` cannot contain interactive children — put the `native:pressable`
outside it.

## Class order is Prettier's
`prettier-plugin-tailwindcss` sorts utilities into its canonical order via `composer lint`. Do not
hand-order class lists and do not try to preserve an existing order.

## `border-dashed` is unsupported by the renderer
It does not render dashed. Use a solid border, or compose a segmented rule from `native:rect` as
`components/post-divider.blade.php` does. Do not add a new `border-dashed` without that workaround.

## Theme tokens, not raw colours
Use `bg-theme-*` / `text-theme-*` from `config/native-ui.php`. Do not add raw hex values, `dark-color`
props, or core Tailwind palette classes such as `text-red-500` or `bg-pink-800/55`.

## Accessibility is `a11y-label`
Native views do not take `aria-*` or `role`. Every `native:pressable` needs an `a11y-label`, and
truncating text needs `max-lines`. A pressable with only a punctuation glyph as content still needs a
label.

## No duplication across blades
If the same markup appears in two screens, extract an anonymous component. Verified seals, author
rows, notification rows, metric rows and number formatting all belong in one place.

## kebab-case filenames
Screen views are `native/<screen>.blade.php`; shared pieces are `components/<name>.blade.php`.

## No hostnames or secrets in blades
Read the API host from `config('services.pinkary.url')`. Never hardcode a domain.