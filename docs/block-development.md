# CELINA block development

## Generate

- Static: `pnpm block:create -- resource-list "Resource List"`
- Dynamic: `pnpm block:create -- jobs-embed "Jobs Embed" --dynamic`

The slug must be lowercase kebab-case, is registered as `celina/<slug>`, and
cannot collide with an existing directory or `blocks.json` entry. The
generator publishes the new block files and the sorted `blocks.json` registry
as one recoverable transaction. Ordinary failures roll back; if work is
interrupted, the next generator run recovers the transaction before creating
another block.

## Generated files

Every block gets `block.json`, `index.tsx`, `edit.tsx`, editor/frontend SCSS,
and a namespace test. Static blocks get `save.tsx`; dynamic blocks get
`render.php` and must escape output with WordPress functions.

## Completion checklist

1. Replace generic `content` metadata with the approved attributes.
2. Use standard WordPress controls and `useBlockProps`.
3. Use CELINA CSS variables; do not introduce a second token system.
4. Add focused unit tests for attribute normalization and saved markup.
5. For dynamic output, add WordPress integration tests for empty, normal,
   bounded, and escaped cases.
6. Add editor save/reload and frontend checks when behavior is user-visible.
7. Run the complete verification sequence:

   ```bash
   pnpm build
   pnpm wp:start
   pnpm exec wp-env run tests-cli --env-cwd=wp-content/plugins/celina-blocks composer install
   pnpm check
   pnpm test:unit
   pnpm test:integration
   pnpm test:e2e
   pnpm test:e2e:content
   pnpm package
   ```

`pnpm package` creates deterministic, installable theme and plugin ZIP files
plus `dist/SHA256SUMS`. Verify a clean ZIP installation before handing a
release to another team; see `docs/release-handoff.md`.

Block text is editor content, not hardcoded CELINA production copy. Fixtures
remain synthetic.

## Structured text attributes

Use `src/shared/rich-content-editor.tsx` for an HTML attribute that can contain
multiple paragraphs or lists. A single `RichText tagName="div"` treats sibling
block elements as rich-text formats and may merge their boundaries when edited.
Keep `RichText` for a single heading, paragraph or short inline label.

The shared editor opens an isolated `BlockEditorProvider` with core text blocks,
its own Undo/Redo history and scoped format toolbar. On explicit Apply it stores
HTML back in the original attribute; its temporary block delimiters are removed.
Cancel/no-op Apply preserve the original value. Existing static save functions
and dynamic PHP renderers stay unchanged, so no block deprecation or managed
content migration is needed for this editor-only change.

Cover old HTML, multiple paragraphs, unordered/ordered/nested lists, inline
formatting, cancel, undo/redo, and editor save/reload/frontend behavior. The
regression suite is `tests/e2e/rich-content.spec.ts`.

## Stylesheet caching

Registration versions plugin-owned frontend, editor and view styles by the
SHA-256 of the served file, including RTL files when selected by WordPress.
Keep this behavior when changing registration. Release ZIPs normalize file
timestamps, so `filemtime()` or the fixed `block.json` version cannot reliably
invalidate browser caches. JavaScript retains the build-generated asset version.
CSS-only updates do not require a managed-content version bump.

## Navigation feedback

Preserve the REDESIGNED resting appearance. Clickable cards use the shared
`src/shared/_card-link.scss` stretched-link accent: an inset citrus outline
on hover and keyboard focus, without resizing the card. Cards without a
destination remain static. Keep secondary phone, email and category links
independently usable.

Buttons on light surfaces use stone with white text for hover/focus; on dark
or photo-dark surfaces they invert to white with dark text. Keep keyboard
focus rings and reduced-motion support. Test the rendered cascade in WordPress,
including native Gutenberg buttons, article navigation, filters and pagination;
changing `border-color` is ineffective on a card without a visible border.

## Hero attachment delivery

Hero remains a static Gutenberg block: keep `save.tsx` and its attribute schema
compatible with existing saved content. The plugin's `includes/hero.php` filter
resolves a selected `backgroundId` with `wp_get_attachment_image_url( ..., 'full' )`
at render time, allowing attachment filters (including Cloudflare Images) to
supply the delivery URL. It changes only the owning hero section's existing
`background-image` declaration; copy, descendants, other styles and stored page
content remain intact. The filter runs at priority 20, after the theme's
priority-10 heading and emphasis filters.

An empty/removed `backgroundUrl`, missing/non-image attachment, unresolved or
unsafe URL, or unexpected markup retains the original saved HTML. URL-only
legacy backgrounds keep working. CSS URL delimiters are percent-encoded and
the HTML tag processor escapes the style attribute; keep query-string
ampersands intact until that final HTML escaping step. No CDN account, image
upload, lazy loading, content migration or Cloudflare-specific URL is introduced.

`tests/integration/HeroBackgroundTest.php` covers WordPress attachment filters,
original attachment fallback, CDN query strings and CSS delimiters, style and
descendant preservation, removed media, and saved-content serialization.
