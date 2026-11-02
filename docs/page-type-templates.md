# CELINA page-type templates

This guide describes how WordPress routes CELINA page types, how those routes
are framed, and how editors use the reusable Gutenberg compositions. The local
fixtures and examples in this document are synthetic and disposable.

## Route map

| Route | WordPress hierarchy | CELINA file | Fixture |
| --- | --- | --- | --- |
| `/` | static front page | `front-page.php` | Fixture Home |
| `/fixture-news/` | configured posts index | `home.php` | Fixture News |
| `/category/fixture-news/` | category archive | `category.php` | Fixture News |
| `/category/company-updates/` | selected news category archive | `category.php` | Company Updates |
| `/fixture-news-1/` | single post | `single-post.php` | Fixture News 1 |
| `/fixture-about/` | standard page | `page.php` | Fixture About |
| `/fixture-missing-route/` | missing route | `404.php` | Page not found |

Each owned route uses `inc/template-renderer.php` to keep the GeneratePress
header, footer, main landmark, and hook lifecycle. The renderer also applies
the no-sidebar and full-width policy. Routes outside this table continue
through the parent theme hierarchy.

## PHP templates, blocks, and patterns

These layers have deliberately different responsibilities:

- **PHP templates** select a route and provide its document framing. They own
  archive loops, article metadata, the 404 message, and the shared shell; they
  do not contain editable page copy.
- **Gutenberg blocks** are the editable units. `celina-blocks` registers 22
  CELINA blocks: 15 editor-facing blocks, six nested item blocks, and one
  inserter-hidden legacy BambooHR compatibility block. They cover the core
  sections, card collections, timeline, resources, editorial article layout,
  contact, and third-party placeholders listed in `docs/editor-guide.md`; News
  Grid queries current published posts at render time.
- **Patterns** are starter compositions stored in
  `wordpress/themes/celina-child/patterns/`. Four generic blueprints support
  future content, while 17 canonical patterns reproduce the agreed managed
  pages and articles. Inserting a pattern copies normal block markup into the
  editor; it is not a runtime dependency or a locked template. The managed
  content command applies the canonical patterns convergently.

The front page and standard-page fixtures therefore remain editable content,
while `home.php`, `category.php`, and `single-post.php` provide the framing
needed for query-driven archives and articles.

Article navigation follows publication date, then post ID to break ties, matching
the news list. **Previous article** leads to the immediately older post and
**Next article** to the immediately newer one. Middle articles show both links;
the newest shows only Previous, the oldest only Next, and a lone article neither.
Only published posts participate; the untouched WordPress sample is excluded.
Navigation cards keep the theme typography and colours, wrap long titles, and
stack at widths up to 48rem.

## Editor workflow

1. Sign in to the disposable local site at `/wp-admin/` with `admin` and
   `password`.
2. Create a page or post and open the block inserter.
3. Select **Patterns**, choose the **CELINA** category, and insert the pattern
   matching the content type.
4. Edit the inserted CELINA and core blocks as ordinary Gutenberg content.
   Change the News Grid category, count, and ordered category navigation in its
   block settings when needed. For the Corporate News page, select Company
   Updates, Manufacturing, and Community in that order after those categories
   exist; reusable patterns cannot safely carry installation-specific term
   IDs.
5. Save the draft, reload the editor, and verify that the blocks remain. The
   local E2E suite exercises this save/reload path and deletes its temporary
   entities with `runWithCleanup`.

The synthetic bootstrap is convergent: it repairs only the named fixture
pages, posts, Fixture News category tree, local article attachment, author
presentation metadata, and menu entries. It assigns the resolved category IDs
to the disposable News Grid in the approved order. Re-running it is safe and
does not import or overwrite unrelated content.

For a new post, typing `/article` inserts the unlocked **CELINA Article
Layout** starter. The **CELINA article** pattern uses the same wrapper and is
restricted to Posts. The single-post PHP template adds the article title,
category, publication date, reading time, tags, featured media, author byline,
and available previous/next links around that editor-owned body.

## Adding a future page type

Start with the official WordPress [classic theme template
hierarchy](https://developer.wordpress.org/themes/classic-themes/basics/template-hierarchy/).

1. Identify the most specific hierarchy file for the new route (for example,
   `search.php` or `tag.php`) and confirm that the route is outside the current
   table.
2. Add a small routing file that calls `celina_template_open()` and
   `celina_template_close()`, with route-specific content in a template part.
3. Add only the fixture and contract coverage needed to prove selection,
   status, accessibility, and responsive behavior.
4. Extend `content-templates.css` and this route table only when the new page
   type needs presentation or documentation.
5. Keep editable copy in blocks or a pattern; do not hard-code a second page
   composition in PHP. Review the fallback behavior for every hierarchy branch
   that remains parent-owned.

## Article-generator reference boundary

We adopted the useful, local ideas from the article-generator reference:

- modular compositions with clear responsibilities;
- versioned, reviewable source files;
- deterministic local output and a clear source/state boundary.

We rejected mechanisms that do not belong in this workbench:

- LLM or scraper-generated content;
- a runtime article-generator dependency;
- direct MySQL or SSH writes from the theme;
- imported production HTML, remote synchronization, or force-overwrite flows;
- a second JSON definition for content already represented by Gutenberg blocks.

## Synthetic-only boundary

Fixtures use names such as `Fixture Home`, `Fixture News`, and
`example.test`. They are disposable local data used by PHP contracts and the
isolated Playwright environment. This repository must not receive CELINA
production credentials, production database exports, customer data, private
media, tracking snippets, or deployment targets. Production installation and
release approval remain outside this workbench.
