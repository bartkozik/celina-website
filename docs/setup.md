# Local setup

This guide starts a disposable local CELINA WordPress site. It never connects
to production and uses only repository-controlled fixtures or managed content.

## Prerequisites

- Node.js `22.23.2`
- pnpm `10.15.0`
- Docker Desktop or another Docker engine compatible with `@wordpress/env`
- Git

```bash
node --version
pnpm --version
docker info
git --version
```

Run all commands from the `wordpress-workbench` repository root. With nvm,
activate the required Node version before installing dependencies:

```bash
nvm install
nvm use
```

`.npmrc` enables strict engine checks, so a different Node version can prevent
installation. If pnpm is unavailable, enable the pinned release:

```bash
node ops/security/with-safe-ca.mjs corepack enable
node ops/security/with-safe-ca.mjs corepack prepare pnpm@10.15.0 --activate
```

## Install dependencies

```bash
node ops/security/with-safe-ca.mjs pnpm install --frozen-lockfile
pnpm build
```

The wrapper excludes the compromised eMudhra CA keys before Node makes HTTPS
connections. Use it for commands that download dependencies or start wp-env,
for example `node ops/security/with-safe-ca.mjs pnpm wp:start`. It protects that
command and its children; it does not change your shell profile or Docker daemon.
See the CA remediation runbook (document excluded from this source export) for checks and remaining
infrastructure work.

A local `.env` is not required. The scripts do not automatically load the root
`.env.example`; custom `WP_ENV_PORT` / `WP_ENV_TESTS_PORT` values must be exported
in the shell and kept consistent across start, content commands, and stop.
`ops/.env` belongs to hosted-demo operations and is not used for local startup.
The first start needs network access to download WordPress, Docker images, and
GeneratePress `3.6.1`. PHP `8.2` and the database run in Docker; host PHP and
Composer are not required. The source checks use host PHP if available, or a
Docker PHP fallback otherwise.

The `build/` directory is generated and ignored. Re-run `pnpm build` after
changing Gutenberg block source.

## Choose a local profile

Synthetic development/demo data:

```bash
pnpm wp:start
```

Approved CELINA page compositions without synthetic fixture import:

```bash
pnpm wp:content:start
pnpm exec wp-env run cli wp rewrite structure '/%postname%/' --hard
pnpm content:plan
pnpm content:apply
pnpm content:plan
```

Open <http://localhost:8888> or <http://localhost:8888/wp-admin/>. The
development-only credentials are `admin` / `password`.

Both start commands use the same disposable database for a checkout. Switching
commands does not implicitly delete it. Synthetic fixtures assign their own
homepage, so switching to managed content can report
`CONFLICT reading_options_conflict`. To preserve existing content, use
**Settings → Reading** to select the managed Home page and Corporate News posts
page after those pages have been created, then rerun `pnpm content:plan`.

Only if all database content is disposable, reset the development database and
recreate the complete demo:

```bash
pnpm wp:stop
pnpm exec wp-env clean development
pnpm wp:content:start
pnpm exec wp-env run cli wp rewrite structure '/%postname%/' --hard
pnpm content:plan
pnpm content:apply
pnpm content:plan
```

`clean development` resets the normal local database, including pages, users,
and settings. It does **not** remove uploaded files from disk. `clean all`
also resets the test database and is not necessary to switch the normal demo.
Never run either reset when you need to retain local database content.

The managed homepage is served at `/`; `/home/` is its stored page slug and may
redirect to `/`. The synthetic fixture page is at `/fixture-home/`.

## Update an existing checkout

After pulling application changes, run:

```bash
pnpm install --frozen-lockfile
pnpm build
pnpm wp:content:start
pnpm content:plan
```

Review the plan before `pnpm content:apply`, then check the plan again for
`NOOP`. Theme PHP/CSS changes are mounted directly, block assets need a rebuild,
and pattern changes need managed-content synchronization to reach saved pages.
`source_changed_without_version_bump` means a pattern changed without an
increased manifest version; rebuilding or restarting cannot resolve that
conflict. See [managed-content.md](managed-content.md#human-controlled-recovery).
Use the explicit reset above only for disposable data.

## Develop Gutenberg blocks

```bash
pnpm dev
```

Create a new static or dynamic block skeleton:

```bash
pnpm block:create -- quote-card "Quote Card"
pnpm block:create -- latest-items "Latest Items" --dynamic
```

The generator updates `blocks.json` and the new source directory as one
recoverable transaction. Generated files still require product-specific
attributes, markup, styling, and tests.

## Verification

Source and unit gates:

```bash
pnpm check
pnpm test:unit
```

Plugin integration:

```bash
pnpm wp:start
pnpm exec wp-env run tests-cli --env-cwd=wp-content/plugins/celina-blocks composer install
pnpm test:integration
```

The Composer-created plugin `vendor/` directory is ignored.

Browser acceptance:

```bash
pnpm exec playwright install chromium
pnpm test:e2e
pnpm test:e2e:content
```

On Linux, use `pnpm exec playwright install --with-deps chromium` if browser
system libraries are missing. Chromium is needed for browser tests, not for
starting the site.

The first run can take several minutes while WordPress and Chromium resources
are prepared. Each wrapper creates and cleans only its own checkout-local
environment and refuses to reuse an unrelated listener. The normal demo on
port `8888` can remain running.

| Purpose | Development port | Test port |
| --- | ---: | ---: |
| Normal local demo | 8888 | 8891 |
| Synthetic browser acceptance | 8892 | 8893 |
| Managed-content acceptance | 8894 | 8895 |
| Package clean-install check | 8896 | 8897 |

If another checkout owns `8894`/`8895`, keep its stack running and select an
unused pair for this checkout's complete lifecycle:

```bash
CELINA_CONTENT_E2E_PORT=18994 \
CELINA_CONTENT_E2E_TESTS_PORT=18995 \
pnpm test:e2e:content
```

## Build release packages

```bash
pnpm package
cd dist
shasum -a 256 -c SHA256SUMS
```

The package command rebuilds the plugin and creates deterministic theme/plugin
ZIPs under ignored `dist/`. See release-handoff.md (document excluded from this source export) for the
installation boundary.

## Stop and restart

```bash
pnpm wp:stop
pnpm wp:content:start
```

Use `pnpm wp:start` instead when resuming the synthetic profile. `wp:start`
is convergent: it repairs the named synthetic fixtures and does not import
production content.

## Troubleshooting

### Docker is unavailable

Start Docker, confirm `docker info` succeeds, and rerun the failed command.

### Chromium is missing

```bash
pnpm exec playwright install chromium
```

### A port is already in use

Stop the process or other checkout that owns the listed port. Test wrappers
fail closed instead of treating a foreign listener as CELINA.

### Block changes do not appear

Run `pnpm build` for a one-time build or `pnpm dev` while editing, then reload
WordPress. Blocks register from `wordpress/plugins/celina-blocks/build/`.

### Managed page URLs return 404

The content start command flushes rules but does not choose a permalink
structure. Enable the documented routes:

```bash
pnpm exec wp-env run cli wp rewrite structure '/%postname%/' --hard
```

### The homepage shows fixtures or a content plan reports a conflict

Check which profile you started. `pnpm wp:start` reapplies fixture Reading
settings. Follow the profile-switching instructions above for
`reading_options_conflict`, and [managed-content.md](managed-content.md) for
ownership, manual-edit, or blueprint-version conflicts.

### The header shows the site name instead of the CELINA logo

The header logo is the WordPress **Site Identity → Logo** setting of each
installation, not a theme file. A fresh local database has none, so the theme
falls back to the site name. Select the approved logo under
**Appearance → Customize → Site Identity → Logo**, or run:

```bash
pnpm exec wp-env run cli wp eval 'require_once ABSPATH . "wp-admin/includes/image.php"; require_once ABSPATH . "wp-admin/includes/file.php"; require_once ABSPATH . "wp-admin/includes/media.php"; $tmp = wp_tempnam(); copy( get_stylesheet_directory() . "/assets/images/celina-logo-white.png", $tmp ); $id = media_handle_sideload( [ "name" => "celina-logo-white.png", "tmp_name" => $tmp ], 0, "CELINA" ); update_post_meta( $id, "_wp_attachment_image_alt", "CELINA" ); set_theme_mod( "custom_logo", $id );'
```

This changes only the local database.
