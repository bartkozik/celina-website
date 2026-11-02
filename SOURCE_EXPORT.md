# Source export notes

This source snapshot retains the actual Gutenberg plugin, GeneratePress child theme, editable patterns, managed-content workbench, local lifecycle scripts, deterministic package builder and application tests. Block names, attributes, editor/save/render logic, CSS layout and content-management algorithms are preserved. No standalone SPA or replacement editor was created.

Sanitation is confined to source export boundaries:

- Client photographs and logo bytes become neutral local PNG placeholders; original photo paths, matching references and literal test contracts are consistently updated.
- Personnel names, addresses, phone/email contacts and client links use synthetic values.
- Configured service account identifiers are inert placeholders. The application keeps its existing unconfigured fallback UI. Connector validation tests use synthetic, format-valid values with the original mocks.
- Private CI, hosting/operations, hosted performance/security tooling and internal client delivery reports are excluded. One mixed package/hosted-gates test file is excluded with that infrastructure; application test assertions remain.
- Matching test literals and the exact expected placeholder-logo checksum change with the sanitized data. Checks for forbidden external media remain, using a separate inert hostname.
- Scripts and TypeScript inputs for excluded operations are removed from configuration; dependency versions and lockfiles are unchanged.

Validation uses Node 22.23.2 and pnpm 10.15.0, matching the original pins:

- Frozen dependency install with lifecycle scripts disabled: passed.
- `pnpm test:unit --maxWorkers=2`: **337 tests passed in 44 files**.
- `pnpm typecheck`: passed.
- `pnpm build`: real WordPress/Gutenberg production assets built successfully.
- `pnpm check`: JavaScript/style lint, typechecking, PHP lint and all ten offline PHP fixture contracts passed. The fixture contracts were rerun successfully after the final contact/link redactions.

PHP checks use disposable local containers. WordPress runtime, editor save/reload, live WordPress integration suites, browser acceptance and deployment were not run for this export. Third-party integrations were not contacted.

Use a fresh disposable local database. Managed-content versions are retained from the source snapshot; this export is not an upgrade package for an existing editorial or production installation. Private Git history, deployed WordPress content and database exports are not included. Brand/project names and technical namespaces remain to identify the real work. This repository has a fresh export history. Original attribution is retained; no new license is asserted.
