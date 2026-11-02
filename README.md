# CELINA — WordPress website source

The actual source of a corporate website rebuild: a GeneratePress child theme, a native Gutenberg blocks plugin, editable page patterns and a reproducible local WordPress workbench. It preserves the original WordPress application and editor behavior.

Bartosz Kozikowski was the sole developer responsible for concept, design, implementation, client collaboration and deployment work. Third-party WordPress, GeneratePress and package dependencies remain separately attributed.

## Architecture

- `wordpress/plugins/celina-blocks`: 22 registered block types, React/TypeScript editor controls, saved markup, dynamic PHP renderers, Sass and block tests. This includes a hidden legacy migration block.
- `wordpress/themes/celina-child`: GeneratePress integration, responsive templates, design tokens, navigation, styles and editable page/section patterns.
- `workbench/content`: the actual managed-content manifest, planning, conflict detection, snapshot and rollback code.
- `scripts/wp`, `fixtures`: local WordPress lifecycle and original synthetic fixtures.
- `scripts/package`: deterministic installable plugin/theme packaging.
- `tests`: existing unit, PHP contract, WordPress integration and editor/browser tests.

WordPress, PHP 8.2, Gutenberg, React 18, TypeScript, Sass, GeneratePress 3.6.1, Vitest and Playwright. Package versions and lockfiles are retained from the source.

## Local setup

Use Node `22.23.2`, pnpm `10.15.0` and Docker. The parent GeneratePress theme is installed from its public distribution; it is not included as project-authored code.

```sh
pnpm install --frozen-lockfile
pnpm build
pnpm wp:start
```

The original synthetic fixture profile runs at `http://localhost:8888`, with the editor at `/wp-admin/` and local-only credentials `admin` / `password`. `wp:start` creates or converges disposable local content; use a fresh local environment when reviewing the snapshot.

To inspect the real managed page compositions in a disposable local environment, use `pnpm wp:content:start`. The original `content:plan` and `content:apply` commands plan and apply managed-content changes. Do not run them against an existing editorial database without reviewing the plan.

```sh
pnpm typecheck
pnpm test:unit
pnpm build
pnpm check
pnpm test:integration
pnpm test:e2e
pnpm test:e2e:content
pnpm package
```

PHP and browser/integration gates need the corresponding local PHP or Docker/WordPress services. See `SOURCE_EXPORT.md` for checks actually performed on this source snapshot. Historical counts in retained source documentation are not fresh verification of this export.

## Sanitized source snapshot

Original block names (`celina/*`), attributes, editor components, PHP templates and saved-markup structure are retained. Client photographs and the logo are replaced with neutral local images; named personnel, contacts and external client links use placeholders. Corresponding test literals and asset references are updated consistently. The real visual composition remains, with placeholder media in place of client photography.

Configured HubSpot and Paycor account identifiers are inert placeholders, so these blocks use their existing unconfigured states. Connector implementation is retained for review; valid service configuration would enable external requests. Private hosting, CI/deployment operations, hosted performance/security tooling and internal delivery reports are excluded. No database dump, production credentials or Git history is included.

Original attribution and third-party notices are preserved. No new license is asserted by this snapshot. Published as a sanitized source snapshot with a fresh export history.
