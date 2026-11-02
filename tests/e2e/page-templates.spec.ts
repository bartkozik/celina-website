import AxeBuilder from '@axe-core/playwright';
import { severeAxeViolations } from '../support/accessibility';
import { expect, test, type FrameLocator, type Page } from '@playwright/test';
import { runWithCleanup } from '../support/run-with-cleanup';
import {
  currentEditorEntity,
  deleteEditorEntity,
  expectDraftSaved,
  logIn,
  prepareEditor,
} from '../support/wordpress-editor';

const routes = [
  { marker: 'front-page', path: '/', title: 'Fixture Home', status: 200 },
  {
    marker: 'home',
    path: '/fixture-news/',
    title: 'Fixture News',
    status: 200,
  },
  {
    marker: 'category',
    path: '/category/fixture-news/',
    title: 'Fixture News',
    status: 200,
  },
  {
    marker: 'category',
    path: '/category/company-updates/',
    title: 'Company Updates',
    status: 200,
  },
  {
    marker: 'single-post',
    path: '/fixture-news-1/',
    title: 'Fixture News 1',
    status: 200,
  },
  {
    marker: 'page',
    path: '/fixture-about/',
    title: 'Fixture About',
    status: 200,
  },
  {
    marker: '404',
    path: '/fixture-missing-route/',
    title: 'Page not found',
    status: 404,
  },
] as const;

const viewports = [
  { name: 'mobile', width: 375, height: 812 },
  { name: 'desktop', width: 1440, height: 900 },
] as const;

const sidebarSelector = '.widget-area, #left-sidebar, #right-sidebar, .sidebar';

async function insertPattern(
  page: Page,
  patternName: string
): Promise< void > {
  await page
    .getByRole( 'button', { name: 'Block Inserter', exact: true } )
    .click();
  await page.getByRole( 'tab', { name: 'Patterns', exact: true } ).click();
  await page
    .getByRole( 'searchbox', { name: 'Search', exact: true } )
    .fill( patternName );
  await page.getByText( patternName, { exact: true } ).click();
}

async function expectArticlePattern( canvas: FrameLocator ): Promise< void > {
  const article = canvas.locator( '[data-type="celina/article-layout"]' );
  const children = article.locator( '[data-type]' );

  await expect( article ).toHaveCount( 1 );
  await expect( children ).toHaveCount( 7 );
  expect(
    await children.evaluateAll( ( blocks ) =>
      blocks.map( ( block ) => block.getAttribute( 'data-type' ) )
    )
  ).toEqual( [
    'core/paragraph',
    'core/heading',
    'core/paragraph',
    'core/image',
    'core/pullquote',
    'core/paragraph',
    'celina/media-contact',
  ] );
  await expect( article.locator( '[data-type="core/paragraph"]' ) ).toHaveCount(
    3
  );
  await expect( article.locator( '[data-type="core/heading"]' ) ).toHaveCount(
    1
  );
  await expect( article.locator( '[data-type="core/image"]' ) ).toHaveCount(
    1
  );
  await expect( article.locator( '[data-type="core/pullquote"]' ) ).toHaveCount(
    1
  );
  await expect(
    article.locator( '[data-type="celina/media-contact"]' )
  ).toHaveCount( 1 );
}

for ( const viewport of viewports ) {
  for ( const route of routes ) {
    test( `${ viewport.name } ${ route.marker } ${ route.path } route is accessible and framed`, async ( {
      page,
    } ) => {
      await page.setViewportSize( viewport );
      const response = await page.goto( route.path, {
        waitUntil: 'domcontentloaded',
      } );

      expect( response?.status() ).toBe( route.status );
      await expect(
        page.locator( `[data-celina-template="${ route.marker }"]` )
      ).toHaveCount( 1 );
      await expect( page.locator( 'h1:visible' ) ).toHaveCount( 1 );
      await expect( page.locator( 'h1:visible' ) ).toHaveText( route.title );
      await expect( page.locator( sidebarSelector ) ).toHaveCount( 0 );

      const hasHorizontalOverflow = await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth
      );
      expect( hasHorizontalOverflow ).toBe( false );

      const results = await new AxeBuilder( { page } ).analyze();
      const severeViolations = severeAxeViolations( results );
      expect( severeViolations ).toEqual( [] );
    } );
  }
}

test( 'editor inserts and persists the CELINA content page pattern', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      await insertPattern( page, 'CELINA content page' );
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      await expect(
        canvas.locator( '[data-type="celina/hero"]' )
      ).toBeVisible();
      await expect(
        canvas.locator( '[data-type="core/paragraph"]' )
      ).toBeVisible();
      await expect(
        canvas.locator( '[data-type="celina/faq"]' )
      ).toBeVisible();

      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      await page.reload( { waitUntil: 'domcontentloaded' } );

      const reloadedCanvas = page.frameLocator(
        'iframe[name="editor-canvas"]'
      );
      await expect(
        reloadedCanvas.locator( '[data-type="celina/hero"]' )
      ).toBeVisible();
      await expect(
        reloadedCanvas.locator( '[data-type="core/paragraph"]' )
      ).toBeVisible();
      await expect(
        reloadedCanvas.locator( '[data-type="celina/faq"]' )
      ).toBeVisible();
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'editor inserts and persists the CELINA article pattern', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      await insertPattern( page, 'CELINA article' );
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      await expectArticlePattern( canvas );

      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      await page.reload( { waitUntil: 'domcontentloaded' } );

      const reloadedCanvas = page.frameLocator(
        'iframe[name="editor-canvas"]'
      );
      await expectArticlePattern( reloadedCanvas );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'managed Corporate News keeps its featured story out of stable latest pages', async ( {
  page,
} ) => {
  const response = await page.goto( '/fixture-news/', {
    waitUntil: 'domcontentloaded',
  } );

  expect( response?.ok() ).toBe( true );
  await expect( page.locator( 'h1:visible' ) ).toHaveText( 'Fixture News' );
  const feature = page.locator( '.celina-news-grid__featured' );
  const latest = page.locator( '.celina-news-grid__items' );
  await expect( feature ).toContainText( 'Fixture News 8' );
  await expect( feature.locator( '.celina-news-card' ) ).toHaveCount( 1 );
  await expect( latest.locator( '.celina-news-card' ) ).toHaveCount( 6 );
  await expect( latest ).not.toContainText( 'Fixture News 8' );
  await expect( latest ).toContainText( 'Fixture News 7' );
  await expect( latest ).toContainText( 'Fixture News 2' );
  await expect( latest ).not.toContainText( 'Fixture News 1' );

  const categoryNavigation = page.locator(
    '.celina-news-grid__navigation[aria-label="News categories"]'
  );
  await expect( categoryNavigation ).toBeVisible();
  await expect( categoryNavigation.locator( 'a' ) ).toHaveText( [
    'All News',
    'Company Updates',
    'Manufacturing',
    'Community',
  ] );
  await expect(
    categoryNavigation.getByRole( 'link', { name: 'All News', exact: true } )
  ).toHaveAttribute( 'aria-current', 'page' );

  const editorialCards = page.locator(
    '.celina-news-grid__featured .celina-news-card, .celina-news-grid__items .celina-news-card'
  );
  await expect( editorialCards ).toHaveCount( 7 );
  await expect(
    editorialCards.locator( '.celina-news-card__category' )
  ).toHaveCount( 7 );
  await expect(
    editorialCards.locator( '.celina-news-card__author-name' )
  ).toHaveText( Array( 7 ).fill( 'CELINA Editor' ) );
  await expect(
    editorialCards.locator( '.celina-news-card__author-title' )
  ).toHaveText( Array( 7 ).fill( 'Corporate Communications Manager' ) );
  await expect( editorialCards.locator( 'time[datetime]' ) ).toHaveCount( 7 );
  await expect(
    latest.locator( '.celina-news-card__media img[alt=""]' )
  ).toHaveCount( 1 );

  const articleLinks = await editorialCards
    .locator( ':is(h2, h3) a' )
    .evaluateAll( ( links ) =>
      links.map( ( link ) => link.getAttribute( 'href' ) )
    );
  expect( new Set( articleLinks ).size ).toBe( 7 );

  const firstPageAxe = await new AxeBuilder( { page } ).analyze();
  expect( severeAxeViolations( firstPageAxe ) ).toEqual( [] );

  const pageTwoResponse = await page.goto( '/fixture-news/page/2/', {
    waitUntil: 'domcontentloaded',
  } );

  expect( pageTwoResponse?.ok() ).toBe( true );
  await expect( page.locator( '.celina-news-grid__featured' ) ).toHaveCount(
    0
  );
  await expect(
    page.locator( '.celina-news-grid__items .celina-news-card' )
  ).toHaveCount( 1 );
  await expect( page.locator( '.celina-news-grid__items' ) ).toContainText(
    'Fixture News 1'
  );
  await expect( page.locator( '.celina-news-grid__items' ) ).not.toContainText(
    'Fixture News 8'
  );
  await expect( page.locator( '.celina-news-grid__pagination' ) ).toBeVisible();
} );

for ( const viewport of [
  { columns: 1, height: 812, name: 'mobile', width: 375 },
  { columns: 2, height: 900, name: 'tablet', width: 800 },
  { columns: 3, height: 900, name: 'desktop', width: 1440 },
] as const ) {
  test( `managed Corporate News uses ${ viewport.columns } columns on ${ viewport.name }`, async ( {
    page,
  } ) => {
    await page.setViewportSize( viewport );
    await page.goto( '/fixture-news/', { waitUntil: 'domcontentloaded' } );

    const columns = await page
      .locator( '.celina-news-grid__items' )
      .evaluate(
        ( grid ) =>
          window.getComputedStyle( grid ).gridTemplateColumns.split( ' ' )
            .length
      );
    expect( columns ).toBe( viewport.columns );
  } );
}

test( 'managed Corporate News category navigation exposes current state and keyboard focus', async ( {
  page,
} ) => {
  await page.goto( '/category/company-updates/', {
    waitUntil: 'domcontentloaded',
  } );

  const navigation = page.locator( '.celina-news-grid__navigation' );
  const companyUpdates = navigation.getByRole( 'link', {
    name: 'Company Updates',
    exact: true,
  } );
  await expect( companyUpdates ).toHaveAttribute( 'aria-current', 'page' );
  await expect( navigation.locator( 'a' ) ).toHaveText( [
    'All News',
    'Company Updates',
    'Manufacturing',
    'Community',
  ] );
  await expect(
    page.locator( '.celina-news-grid__items .celina-news-card' )
  ).toHaveCount( 3 );
  await expect(
    page.locator( '.celina-news-grid__items .celina-news-card__category' )
  ).toHaveText( Array( 3 ).fill( 'Company Updates' ) );

  const allNews = navigation.getByRole( 'link', {
    name: 'All News',
    exact: true,
  } );
  await allNews.focus();
  await page.keyboard.press( 'Tab' );
  await expect( companyUpdates ).toBeFocused();
  expect(
    await companyUpdates.evaluate(
      ( link ) => window.getComputedStyle( link ).outlineWidth
    )
  ).not.toBe( '0px' );

  // Audit the settled keyboard-focus colors, not an intermediate frame of
  // the foreground/background CSS transitions.
  await companyUpdates.evaluate( async ( link ) => {
    await Promise.all(
      link.getAnimations().map( ( animation ) => animation.finished )
    );
  } );

  const axe = await new AxeBuilder( { page } ).analyze();
  expect( severeAxeViolations( axe ) ).toEqual( [] );
} );

test( 'fixture article renders editorial metadata, media, body, and post navigation', async ( {
  page,
} ) => {
  const response = await page.goto( '/fixture-news-1/', {
    waitUntil: 'domcontentloaded',
  } );

  expect( response?.ok() ).toBe( true );
  const article = page.locator( '.celina-single-article' );
  await expect( article.locator( 'h1' ) ).toHaveText( 'Fixture News 1' );
  await expect( article.locator( 'h1' ) ).toHaveCount( 1 );
  await expect( article.locator( '.celina-article-category a' ) ).toHaveText(
    'Company Updates'
  );
  await expect( article.locator( '.celina-article-meta time' ) ).toBeVisible();
  await expect( article.locator( '.celina-article-reading-time' ) ).toHaveText(
    '1 min read'
  );
  await expect( article.locator( '.celina-article-tags a' ) ).toHaveText( [
    'CELINA News',
    'Fixture Editorial',
  ] );
  await expect(
    article.locator( '.celina-article-media img' )
  ).toHaveAttribute( 'alt', 'Synthetic CELINA manufacturing news' );
  await expect( article.locator( '.celina-article-byline__name' ) ).toHaveText(
    'CELINA Editor'
  );
  await expect(
    article.locator( '.celina-article-byline__job-title' )
  ).toHaveText( 'Corporate Communications Manager' );
  await expect( article.locator( '.celina-entry-content h2' ) ).toContainText(
    'Fixture article details'
  );
  await expect( article.locator( '.celina-article-navigation a' ) ).toHaveCount(
    1
  );
  await expect(
    article.locator( '.celina-article-navigation' )
  ).not.toContainText( 'Hello world!' );

  const axe = await new AxeBuilder( { page } ).analyze();
  expect( severeAxeViolations( axe ) ).toEqual( [] );
} );

test( 'untouched WordPress sample content is not a public article', async ( {
  page,
} ) => {
  const response = await page.goto( '/hello-world/', {
    waitUntil: 'domcontentloaded',
  } );

  expect( response?.status() ).toBe( 404 );
  await expect( page.locator( '.celina-single-article' ) ).toHaveCount( 0 );
} );

test( 'publishing categorized news updates the managed index and preserves its single route', async ( {
  page,
} ) => {
  const title = `Managed Browser News ${ Date.now() }`;

  await logIn( page );
  await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );
  const created = await page.evaluate( async ( postTitle ) => {
    const settings = (
      window as typeof window & {
        wpApiSettings?: { nonce?: string; root?: string };
      }
     ).wpApiSettings;

    if ( ! settings?.nonce || ! settings.root ) {
      throw new Error( 'WordPress REST settings unavailable.' );
    }

    const headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
    };
    const categoriesResponse = await fetch(
      `${ settings.root }wp/v2/categories?slug=fixture-news`,
      { headers }
    );
    const categories = ( await categoriesResponse.json() ) as Array< {
      id: number;
    } >;
    const response = await fetch( `${ settings.root }wp/v2/posts`, {
      body: JSON.stringify( {
        categories: [ categories[ 0 ]?.id ],
        content:
          '<!-- wp:paragraph --><p>Synthetic browser article.</p><!-- /wp:paragraph -->',
        excerpt: 'Synthetic browser excerpt.',
        status: 'publish',
        title: postTitle,
      } ),
      headers,
      method: 'POST',
    } );
    const post = ( await response.json() ) as { id?: number; link?: string };

    if ( response.status !== 201 || ! post.id || ! post.link ) {
      throw new Error(
        `Could not create categorized news (${ response.status }).`
      );
    }

    return {
      apiRoot: settings.root,
      id: post.id,
      link: post.link,
      nonce: settings.nonce,
      restBase: 'posts',
    };
  }, title );

  await runWithCleanup(
    async () => {
      await page.goto( '/fixture-news/', { waitUntil: 'domcontentloaded' } );
      await expect(
        page.locator( '.celina-news-grid__featured' )
      ).toContainText( title );
      await expect(
        page.locator( '.celina-news-grid__items' )
      ).not.toContainText( title );

      const singleResponse = await page.goto( created.link, {
        waitUntil: 'domcontentloaded',
      } );
      expect( singleResponse?.ok() ).toBe( true );
      await expect( page.locator( 'h1:visible' ) ).toHaveText( title );

      const categoryResponse = await page.goto( '/category/fixture-news/', {
        waitUntil: 'domcontentloaded',
      } );
      expect( categoryResponse?.ok() ).toBe( true );
      await expect(
        page.getByRole( 'heading', { name: title } )
      ).toBeVisible();
    },
    async () => deleteEditorEntity( page, created )
  );
} );
