# Managed content workflow

The managed-content blueprint commands create and update the seventeen CELINA pages
and posts declared by the workbench manifest. The ten principal pages now use
`celina/redesigned-page-*` aliases backed by Iza's existing Sales pattern files.
The other managed records retain their current sources. The local commands are
deliberately local:
there is no production host, remote SSH target, credential lookup, or deployment
behavior in this workflow.

## Editable media migration

Whole-site `plan` / `plan-safe` also emit a read-only `MEDIA_PLAN`. Before
blueprint apply, `apply` / `apply-safe` import bundled photographs into uploads
and repair **current** saved pages, posts and reusable blocks. This includes
unmanaged editorial records; archival redesign/UI snapshots are excluded.
Group-scoped blueprint commands do not migrate unrelated pages.

The migration preserves current copy, uploaded photos, gallery order, captions,
alt text and image settings. It gives URL-only images native attachment IDs,
materializes CSS hero photographs into Hero settings and converts the two
Contact photo groups to native Cover blocks. Registered CELINA patterns resolve
their images from this installation's attachments without database writes in
ordinary editor requests. No installation-specific IDs are committed to patterns.

For a separately reviewed operation, use WP-CLI in the disposable local stack:

```sh
pnpm exec wp-env run cli wp eval-file /var/www/html/celina-workbench/content/media-command.php plan
pnpm exec wp-env run cli wp eval-file /var/www/html/celina-workbench/content/media-command.php apply REVIEWED_FINGERPRINT
```

Apply rejects stale state, active editor locks, ambiguous/deleted imports,
missing or changed source files, unsupported image placements and held pages.
It uses a migration lock, InnoDB row locks, verified revisions and per-page
receipts (`_celina_media_receipt`). The `_celina_media_imports` option retains
attachment identity even after an accidental Media Library deletion: restore
the missing file/record from backup instead of silently creating a new ID.
A failed run can leave completed imports/pages; rerun plan to inspect the
remaining work. A successful repeated apply imports nothing and changes no pages.
The previous managed hash advances only when it matched the exact previous
content; editorial conflicts remain conflicts.

Demo uses the existing `sync_demo_content` operation after immutable image
deployment. It takes a verified backup and excludes web edits before apply.
Check `MEDIA_APPLIED` and a converged media plan in its log; an exit-20 blueprint
warning is not proof that media migration failed. Media errors are blocking.
Recovery is an explicit reviewed restore using the backup or the receipt's
pre-change revision; image rollback alone does not revert database content.

## Start the content profile

Stop this checkout's disposable `wp-env` stack before switching profiles, then
run:

```bash
pnpm wp:content:start
pnpm exec wp-env run cli wp rewrite structure '/%postname%/' --hard
```

This starts the same theme and blocks plugin as `pnpm wp:start`, but does not
load the synthetic demo fixtures. Neither start command runs `wp-env clean` or
implicitly resets the database. The synthetic start command does reapply its
fixture homepage and Reading settings; the content start command skips that step.

If this disposable checkout was previously started in synthetic-fixture mode,
its existing database remains intact. After stopping that checkout and only if
you intentionally want to discard its local database, clean it explicitly with
`pnpm exec wp-env clean development` before starting the other profile. Never
use that cleanup command against a checkout whose local data you need to retain. It
resets database content, not uploaded files on disk. For a complete reset and
restart sequence or recovery without resetting, see
[local setup](setup.md#choose-a-local-profile).

## Plan before apply

Inspect all canonical content:

```bash
pnpm content:plan
```

Or work with one independently deliverable page group:

```bash
pnpm content:plan -- --group brand-pages
pnpm content:plan -- --group offer-pages
pnpm content:plan -- --group people-contact-pages
pnpm content:plan -- --group news-resources
pnpm content:plan -- --group legal-pages
```

`plan` is read-only. It resolves the registered WordPress patterns, calculates
their exact SHA-256 hashes, inspects every relevant page/post status, and prints
one deterministic operation per manifest item plus totals.

Apply only after reviewing a clean plan:

```bash
pnpm content:apply
```

The same optional `--group` argument works for `content:apply`. Apply creates or
updates only operations classified as safe by a fresh plan, verifies every
write, and returns a non-zero exit code when a source, ownership, category,
option, or write conflict remains.

For an existing page at a version listed in its manifest `redesign_from`, plan
reports `UPDATE redesign_snapshot`. This one-time operation archives the
*current* saved page and metadata as a separate draft titled **(before
redesign)**, then replaces the content of the same page with the REDESIGNED
composition in one database transaction. A previous manual copy change is
captured in the draft; it does not stop this approved transition. Review the
plan and current page before apply. For pages already at their canonical
identity, the active page keeps its ID, public title, slug/URL and Reading
assignment, and Pages displays an admin-only
**(redesigned)** state. The draft has a unique slug and no managed ownership
keys. A repeated apply converges without making another draft. Edits made to
the active page *after* the transition still cause `manual_content_conflict`.

## Operation meanings

- `CREATE missing`: no owned record or slug collision exists; apply may create
  the canonical item.
- `UPDATE version_upgrade`: the manifest version increased and the existing
  record still matches its last applied content and canonical identity.
- `UPDATE redesign_snapshot`: an eligible earlier managed page is archived in
  a draft and replaced transactionally with its REDESIGNED pattern.
- `UPDATE identity_migration`: a page exactly matches the explicitly declared
  prior identity and version, has no manual content edits, and can safely move
  to its new canonical identity.
- `UPDATE adoption`: the reviewed demo Privacy Policy record is identified by
  its declared ID, type, and slug. Its legacy title, status, and parent are
  normalized while canonical content and CELINA metadata replace that page.
  Missing, duplicate, mismatched, or already marked candidates remain
  conflicts.
- `NOOP current`: the record already matches the current blueprint.
- `NOOP deferred_missing_pages`: Reading settings wait until both the managed
  homepage and Corporate News page exist.
- `CONFLICT`: nothing is overwritten for that item. The reason and details
  identify the recovery action.

Every managed record stores `_celina_blueprint_key`,
`_celina_blueprint_version`, and `_celina_applied_content_hash`. The pattern
file is the sole source of serialized blocks and copy; the manifest contains
identity, version, group, category, assignment, and pattern-slug metadata only.

## Reviewed page rollback

Image rollback does not restore WordPress content. To restore one page from its
**(before redesign)** draft in a disposable local environment, find that
draft's post ID, then run the read-only plan from the repository root:

```bash
pnpm exec wp-env run cli wp eval-file /var/www/html/celina-workbench/content/redesigned-command.php plan-rollback SNAPSHOT_ID
```

Review the JSON result. `action: restore` includes the active page ID and a
64-character `fingerprint`. Only after reviewing that exact result, use its
fingerprint in the write command:

```bash
pnpm exec wp-env run cli wp eval-file /var/www/html/celina-workbench/content/redesigned-command.php rollback SNAPSHOT_ID REVIEWED_FINGERPRINT
```

Rollback verifies the snapshot and unchanged active-page state again inside a
transaction. It returns a conflict if the page changed after redesign; it
does not erase later client edits. A successful restore preserves the original
page ID, content and metadata and sets `_celina_redesign_hold`. Normal sync
then reports `redesign_rollback_hold` and leaves that page alone, including on
retries. Repeating rollback returns `noop` once that snapshot is restored.
There is no automatic hold removal. After reviewing the restored page and the
intended next source, a local operator can explicitly clear the hold with:

```bash
pnpm exec wp-env run cli wp post meta delete PAGE_ID _celina_redesign_hold
```

The next sync can then plan the REDESIGNED transition again.
For the private demo, use the host backup and operation lock in
ops/README.md (document excluded from this source export) before any content recovery; the local
`wp-env` commands above do not target that host.

## Human-controlled recovery

Outside the explicitly declared one-time REDESIGNED transition above, the
manager never restores Trash, reparents a record, discards manual edits, or
downgrades a blueprint version. Unmanaged slugs remain conflicts except for the
one reviewed demo Privacy Policy record identified by its declared ID, type, and
slug and with no CELINA metadata keys. The only rename it performs is an
explicitly declared identity migration: the owned page must match the exact
prior slug, title, and source version and still match its last applied content
hash. A collision at the new slug remains a conflict. Resolve any unapproved
collision manually and run `content:plan` again. Typical examples:

- restore and republish a managed item from Trash, or permanently delete it if
  clean recreation is intended;
- return a renamed/reparented item to its canonical identity;
- move or rename unmanaged content occupying a canonical slug;
- reconcile manual content edits, then update the stored applied hash only when
  the reviewed page really matches the canonical pattern;
- increment the manifest version whenever canonical pattern content changes.

The `Company Milestones` category is created only when the exact name/slug pair
is absent. A same-name/different-slug or same-slug/different-name term is a
conflict and is never renamed. `show_on_front`, `page_on_front`, and
`page_for_posts` are changed together only from an absent or clean WordPress
bootstrap tuple, and only after both managed pages resolve safely.
