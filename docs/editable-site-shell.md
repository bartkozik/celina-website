# Global header and footer editing

CEL-30 adds an optional Gutenberg shell to the GeneratePress child theme. Installing the package alone does not switch the site or write content. Existing PHP rendering remains active until an administrator explicitly initializes the parts.

## Ownership and access

Code, default compositions and styles live in GitLab. Once initialized, the header/footer content lives in WordPress template-part records and ordinary editor saves take precedence. Each part is shared across pages, articles, archives and the 404 page: saving changes the entire site.

Open **Appearance > Header & Footer** using an account with `edit_theme_options` (normally Administrator). The standard WordPress Editor role does not have this capability. This release does not grant additional privileges to existing users. Use the named Header and Footer links to open Gutenberg.

Add, remove and reorder ordinary text, images, buttons, navigation links, groups and social links with Gutenberg's inserter and List View. Initialization snapshots the approved CELINA fallback links when a menu is unassigned or empty; it never substitutes WordPress’s automatic page list. It copies only currently displayed menu levels (two for primary navigation, one elsewhere); configured social menus that the old footer did not render remain unshown until an editor adds social content. Use the prepared layout groups to preserve spacing and automatic wrapping. Navigation links and submenus are edited inside Navigation blocks. The footer year updates automatically; the adjacent copyright wording is ordinary editable text. Changing the entire design or introducing an unsupported component may need a developer.

Keep the outer header/footer groups and the prepared inner containers. Desktop/mobile behavior is provided by the theme, a native Details disclosure and one shared Navigation block. The mobile menu opens below the header, matching the existing site; the utility strip scrolls away and the main header remains sticky. Edit the single **Header button** Buttons group in List View. Its label, destination and button content also render inside the open mobile menu automatically. Removing that group removes both presentations; no second mobile button is saved. The footer likewise uses one set of editable blocks across screen sizes. Saving a menu or contact change here does not update the legacy Customizer/menu configuration; that configuration remains available for legacy rendering. The Site Logo block uses the shared WordPress logo setting, so replacing it also changes the logo used by the legacy header.

## Release procedure

1. Compare the current target theme/plugin versions, active menus, logo, Customizer fields, saved parts and Additional CSS with the reviewed release. Preserve newer client edits and record any differences. The local checkout is not evidence of the target's current state.
2. Create a current target backup. Generate reviewed packages with `pnpm package` and verify `dist/SHA256SUMS`.
3. Update only the CELINA child theme and any changed CELINA plugin package on the confirmed staging environment. This does not require a full-site migration, database import, user changes or replacement of uploads.
4. Open Appearance > Header & Footer and review initialization before enabling it. The source is the target's current shell settings and menus, not a copied development database. Existing custom parts must be resolved explicitly; never force-replace editorial work.
5. Verify header/footer content, links, editor save/reload, intended account access, desktop/mobile navigation and global rendering. Clear the host/page cache before comparing anonymous output.
6. Promote the reviewed packages to the agreed target with its own backup and controlled initialization. Code deployment and content initialization are separate operations. The existing private demo pipeline does not deploy to WP Engine.

## Upgrading existing 0.2.0 parts

Version 0.2.1 does not rewrite saved parts. A header initialized by 0.2.0 still has its separately editable **Mobile menu button** until an operator reviews it. After backing up the target and deploying 0.2.1, compare its content, links and settings with **Header button**. Preserve any distinct mobile content for a deliberate editorial decision. If redundant, remove only **Mobile menu button** in Gutenberg List View, save and reload. Confirm one Buttons group, no invalid blocks, and matching header/open-mobile-menu links on the frontend after clearing cache. Do not delete the mobile menu panel or Navigation block. Restore the saved part backup/revision if needed. No database migration runs automatically.

The renderer derives the mobile Buttons subtree only for a header with one marked Header button and one marked mobile panel. An existing legacy mobile group or ambiguous markers leave rendering unchanged. Core button styles, content and links are retained, while HTML IDs are removed from the derived copy to avoid duplicate IDs. There is no frontend content relocation.

## Recovery

Use **Use legacy header and footer** to return to the existing PHP shell without deleting the block parts. Re-enabling uses the saved parts, preserving edits. The legacy shell shows its existing menu/Customizer settings; it does not reverse-convert new block edits. To recover an individual editorial change, use the WordPress revision/history tools available in the installed version or restore the narrowly scoped template-part backup.

Do not delete template-part records or replace the full database to roll back this feature. If files must be rolled back, first disable the block shell and retain its saved content.

## Command line

Run on the confirmed target, using the actual administrator account name:

```sh
wp celina shell plan
wp celina shell apply --user=ADMIN_LOGIN
wp celina shell disable --user=ADMIN_LOGIN
```

`plan` is read-only. `apply` initializes missing parts on first use, preserves saved parts on subsequent runs, and re-enables saved parts after disabling. Any existing unowned part is a conflict. `disable` preserves saved records. Mutating commands check the selected account's theme-editing capability; running WP-CLI without `--user` does not bypass the check.

If initialization fails after creating a part, it retains that record and any editor revisions, reports the retained IDs, and leaves the block shell disabled. Review these records before retrying; a partial initialization is a conflict, not permission to overwrite or delete content.

A concurrent operation is refused. If a process is interrupted and leaves a lock, inspect the part records and saved state before recovery; do not blindly remove the lock or force overwrite content. This upgrade requires WordPress 6.5 or later for native block bindings.


## Local verification (12 October 2026)

Validated on disposable WordPress 7.1.3 / PHP 8.2 with GeneratePress 3.6.1. This is local evidence; no WP Engine deployment or client acceptance is implied.

- `pnpm check`, 373 unit tests and the production build pass.
- `pnpm test:content` includes the new shell contract: source conversion, native menu identities, permissions, read-only planning, existing/trashed conflicts, edited/empty content, disable/re-enable, retained partial records/revisions and a four-process initialization race.
- The original focused browser scenarios covered: both template parts open without invalid blocks; insert, remove, reorder and navigation edits survive Save/reload; page, article, search and 404 use saved content; 375/768/1440 px layouts, desktop submenu keyboard use, mobile menu disclosure, active links, accessibility checks and back-to-top focus work. Editor layout geometry and frontend layout were checked separately.
- The initial full synthetic browser run completed 100 scenarios successfully and exposed a faulty new current-link assertion: the fixture Home link uses a redirect alias. The focused test now inserts a canonical URL through Gutenberg; both final shell scenarios pass. All 99 pre-existing synthetic scenarios passed.
- The final native-editor check verifies valid blocks, insert/remove/reorder/save/reload, actual footer-column movement reflected on the frontend, responsive disclosure and disable/re-enable preservation. Seven legacy-comparison scenarios cover sales on/off, unassigned/empty menus, custom logo, dropdown geometry/colors and 375/768/1440 px layouts (2 px geometry tolerance).
- Accessibility checks keep all severe/critical rules enabled; the unchanged legacy orange current-link contrast is narrowly excluded from the contrast assertion to preserve the requested appearance.
- The existing content-browser suite passes 54 scenarios, with one existing conditional skip. Desktop/mobile frontend and footer-editor screenshots were reviewed locally.
- Release ZIPs are generated with `pnpm package`; verify their adjacent `SHA256SUMS` before installation. This change requires the child-theme package; plugin sources are unchanged.

### 0.2.1 single-button regression checks

The follow-up read-only WordPress contract checks one saved Buttons group, derived label/link/content/styles, unique HTML IDs, source deletion, unchanged legacy content and unchanged footer rendering. The editor test loads the current composition on disposable local data, verifies one editable CTA after save/reload, changes and deletes it, checks both frontend locations, and retains footer insertion/removal/column-reordering checks. It restores the original local parts in `finally`. Both focused editor scenarios and all seven legacy visual-parity scenarios pass. Existing 0.2.0 target content still requires the reviewed release step above.
