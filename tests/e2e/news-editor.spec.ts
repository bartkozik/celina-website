import { expect, test, type Locator, type Page } from '@playwright/test';
import { runWithCleanup } from '../support/run-with-cleanup';
import {
  currentEditorEntity,
  deleteEditorEntity,
  expectDraftSaved,
  logIn,
  prepareEditor,
} from '../support/wordpress-editor';

type EditorEntity = Awaited< ReturnType< typeof currentEditorEntity > >;

async function insertNews( page: Page ): Promise< Locator > {
  const inserter = page
    .getByLabel( 'Editor top bar' )
    .getByRole( 'button', { name: 'Block Inserter', exact: true } );
  await inserter.click();
  const search = page.getByRole( 'searchbox', { name: 'Search', exact: true } );
  await search.fill( 'News Grid' );
  await page
    .getByRole( 'listbox', { name: 'Blocks', exact: true } )
    .locator( '.editor-block-list-item-celina-news-grid' )
    .click();
  if ( await search.isVisible() ) {
    await inserter.click();
  }
  const news = page
    .frameLocator( 'iframe[name="editor-canvas"]' )
    .locator( '[data-type="celina/news-grid"]' );
  await expect( news ).toBeVisible();
  await news.locator( 'h2[contenteditable="true"]' ).click();
  if (
    ! ( await page
      .getByRole( 'combobox', { name: 'Layout', exact: true } )
      .isVisible() )
  ) {
    await page.getByRole( 'button', { name: 'Settings', exact: true } ).click();
  }
  await expect(
    page.getByRole( 'combobox', { name: 'Layout', exact: true } )
  ).toBeVisible();
  return news;
}

async function openArticleLink( page: Page, link: Locator ): Promise< Page > {
  await expect( link ).toHaveAttribute( 'target', '_blank' );
  await expect( link ).toHaveAttribute( 'rel', /noopener/ );
  const popupPromise = page.context().waitForEvent( 'page' );
  await link.click();
  const popup = await popupPromise;
  await popup.waitForLoadState( 'domcontentloaded' );
  expect( await popup.evaluate( () => window.opener === null ) ).toBe( true );
  return popup;
}

async function createFixture(
  page: Page,
  entity: EditorEntity,
  restBase: 'posts' | 'categories',
  data: Record< string, unknown >
): Promise< number > {
  const response = await page.request.post(
    `${ entity.apiRoot }wp/v2/${ restBase }`,
    {
      headers: { 'X-WP-Nonce': entity.nonce },
      data,
    }
  );
  expect( response.status() ).toBe( 201 );
  const created = ( await response.json() ) as { id: number };
  expect( created.id ).toBeGreaterThan( 0 );
  return created.id;
}

async function deleteFixtures(
  page: Page,
  entity: EditorEntity,
  posts: number[],
  category: number | null
): Promise< void > {
  const failures: unknown[] = [];
  for ( const [ restBase, id ] of [
    ...posts.map( ( postId ) => [ 'posts', postId ] as const ),
    ...( category ? [ [ 'categories', category ] as const ] : [] ),
  ] ) {
    try {
      const response = await page.request.delete(
        `${ entity.apiRoot }wp/v2/${ restBase }/${ id }?force=true`,
        {
          headers: { 'X-WP-Nonce': entity.nonce },
        }
      );
      expect( response.status() ).toBe( 200 );
      expect( await response.json() ).toMatchObject( {
        deleted: true,
        previous: { id },
      } );
    } catch ( error ) {
      failures.push( error );
    }
  }
  try {
    await deleteEditorEntity( page, entity );
  } catch ( error ) {
    failures.push( error );
  }
  if ( failures.length > 0 ) {
    throw new AggregateError(
      failures,
      'Could not remove all disposable News browser fixtures.'
    );
  }
}

test( 'News article links preserve page edits and refresh after publishing in another tab', async ( {
  page,
} ) => {
  test.setTimeout( 120_000 );
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  const posts: number[] = [];
  const title = `News browser article ${ Date.now() }`;
  const heading = `Unsaved News heading ${ Date.now() }`;
  await runWithCleanup(
    async () => {
      const news = await insertNews( page );
      await news.locator( 'h2[contenteditable="true"]' ).fill( heading );
      const originalUrl = page.url();
      await expect( news.getByRole( 'article' ).first() ).toBeVisible();

      const manage = await openArticleLink(
        page,
        news.getByRole( 'link', { name: /Manage articles/ } )
      );
      await expect( manage ).toHaveURL( /\/wp-admin\/edit\.php$/ );
      await expect(
        manage.getByRole( 'heading', { name: 'Posts', exact: true } )
      ).toBeVisible();
      await manage.close();
      await expect( page ).toHaveURL( originalUrl );
      await expect( news.locator( 'h2[contenteditable="true"]' ) ).toHaveText(
        heading
      );

      const article = await openArticleLink(
        page,
        news.getByRole( 'link', { name: /Add article/ } )
      );
      await expect( article ).toHaveURL( /\/wp-admin\/post-new\.php$/ );
      await prepareEditor( article );
      posts.push( ( await currentEditorEntity( article ) ).id );
      await article
        .frameLocator( 'iframe[name="editor-canvas"]' )
        .getByRole( 'textbox', { name: 'Add title' } )
        .fill( title );
      await article
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await article
        .getByLabel( 'Editor publish' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await expect(
        article.getByRole( 'button', { name: 'Change status: Published' } )
      ).toBeVisible();
      await article.close();

      await expect( page ).toHaveURL( originalUrl );
      await expect( news.locator( 'h2[contenteditable="true"]' ) ).toHaveText(
        heading
      );
      await news
        .getByRole( 'button', { name: 'Refresh articles', exact: true } )
        .click();
      await expect(
        news.getByRole( 'heading', { name: title, exact: true } )
      ).toBeVisible();
      const edit = news.getByRole( 'link', {
        name: `Edit article: ${ title }`,
        exact: true,
      } );
      await expect( edit ).toHaveAttribute(
        'href',
        new RegExp( `post\\.php\\?post=${ posts[ 0 ] }&action=edit$` )
      );
      const editor = await openArticleLink( page, edit );
      await prepareEditor( editor );
      const editedTitle = `${ title } revised`;
      await editor
        .frameLocator( 'iframe[name="editor-canvas"]' )
        .getByRole( 'textbox', { name: 'Add title' } )
        .fill( editedTitle );
      await editor
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Save', exact: true } )
        .click();
      await expect(
        editor.getByTestId( 'snackbar' ).filter( { hasText: /updated/i } )
      ).toBeVisible();
      await editor.close();
      await news
        .getByRole( 'button', { name: 'Refresh articles', exact: true } )
        .click();
      await expect(
        news.getByRole( 'heading', { name: editedTitle, exact: true } )
      ).toBeVisible();
      await expect( news.locator( 'h2[contenteditable="true"]' ) ).toHaveText(
        heading
      );

      await page
        .getByRole( 'button', { name: 'Save draft', exact: true } )
        .click();
      await expectDraftSaved( page );
      await page.reload();
      await prepareEditor( page );
      await expect( news.locator( 'h2[contenteditable="true"]' ) ).toHaveText(
        heading
      );
      await expect(
        news.getByRole( 'heading', { name: editedTitle, exact: true } )
      ).toBeVisible();
      await expect(
        page
          .frameLocator( 'iframe[name="editor-canvas"]' )
          .getByText( 'This block contains unexpected or invalid content.' )
      ).toHaveCount( 0 );
    },
    async () => deleteFixtures( page, entity, posts, null )
  );
} );

test( 'News layouts and an older searched featured story persist without duplicate index cards', async ( {
  page,
} ) => {
  test.setTimeout( 120_000 );
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  const posts: number[] = [];
  let category: number | null = null;
  const suffix = Date.now();
  const categoryName = `News browser category ${ suffix }`;
  const olderTitle = `Historical launch ${ suffix }`;
  await runWithCleanup(
    async () => {
      category = await createFixture( page, entity, 'categories', {
        name: categoryName,
      } );
      const olderId = await createFixture( page, entity, 'posts', {
        title: olderTitle,
        status: 'publish',
        date: '2020-01-01T12:00:00',
        categories: [ category ],
        excerpt: 'Historical article summary.',
      } );
      posts.push( olderId );
      for ( let index = 0; index < 21; index += 1 ) {
        posts.push(
          await createFixture( page, entity, 'posts', {
            title: `Recent article ${ suffix } ${ index }`,
            status: 'publish',
            date: '2025-01-01T12:00:00',
            categories: [ category ],
            excerpt: 'Recent article summary.',
          } )
        );
      }
      const news = await insertNews( page );
      await page
        .getByRole( 'combobox', { name: 'Category', exact: true } )
        .selectOption( { label: categoryName } );
      await expect( news.getByRole( 'article' ) ).toHaveCount( 3 );
      await expect(
        news.getByRole( 'heading', {
          name: `Recent article ${ suffix } 20`,
          exact: true,
        } )
      ).toBeVisible();

      const layout = page.getByRole( 'combobox', {
        name: 'Layout',
        exact: true,
      } );
      await layout.selectOption( 'featured' );
      await expect( news.getByRole( 'article' ) ).toHaveCount( 1 );
      await expect(
        news.getByRole( 'heading', {
          name: `Recent article ${ suffix } 20`,
          exact: true,
        } )
      ).toBeVisible();
      const featured = page.getByRole( 'combobox', {
        name: 'Featured story',
        exact: true,
      } );
      await expect( featured.locator( 'option' ) ).toHaveCount( 21 );
      await expect(
        featured.getByRole( 'option', { name: olderTitle, exact: true } )
      ).toHaveCount( 0 );
      await page
        .getByRole( 'textbox', {
          name: 'Search published articles',
          exact: true,
        } )
        .fill( olderTitle );
      await expect(
        featured.getByRole( 'option', { name: olderTitle, exact: true } )
      ).toHaveCount( 1 );
      await featured.selectOption( String( olderId ) );
      await expect(
        news.getByRole( 'heading', { name: olderTitle, exact: true } )
      ).toBeVisible();
      await expect( news.getByRole( 'article' ) ).toHaveCount( 1 );
      await expect(
        news.getByText( 'Historical article summary.', { exact: true } )
      ).toBeVisible();

      await layout.selectOption( 'news-index' );
      await expect(
        news.locator( '.celina-news-grid__featured article' )
      ).toHaveCount( 1 );
      await expect(
        news.locator( '.celina-news-grid__items article' )
      ).toHaveCount( 6 );
      await expect(
        news
          .locator( '.celina-news-grid__items' )
          .getByRole( 'heading', { name: olderTitle, exact: true } )
      ).toHaveCount( 0 );
      await expect(
        news
          .locator( '.celina-news-grid__items' )
          .getByText( 'Recent article summary.', { exact: true } )
      ).toHaveCount( 0 );
      await page
        .getByRole( 'checkbox', { name: categoryName, exact: true } )
        .check();
      await page
        .getByRole( 'button', { name: 'Save draft', exact: true } )
        .click();
      await expectDraftSaved( page );
      await page.reload();
      await prepareEditor( page );
      await news.locator( 'h2[contenteditable="true"]' ).click();
      await expect( layout ).toHaveValue( 'news-index' );
      await expect( featured ).toHaveValue( String( olderId ) );
      await expect(
        featured.getByRole( 'option', { name: olderTitle, exact: true } )
      ).toHaveCount( 1 );
      await expect(
        page.getByRole( 'checkbox', { name: categoryName, exact: true } )
      ).toBeChecked();
      await expect(
        news
          .locator( '.celina-news-grid__featured' )
          .getByRole( 'heading', { name: olderTitle, exact: true } )
      ).toBeVisible();
      await expect(
        news.locator( '.celina-news-grid__items article' )
      ).toHaveCount( 6 );
      await expect( news.getByRole( 'alert' ) ).toHaveCount( 0 );
      await expect(
        page
          .frameLocator( 'iframe[name="editor-canvas"]' )
          .getByText( 'This block contains unexpected or invalid content.' )
      ).toHaveCount( 0 );
    },
    async () => deleteFixtures( page, entity, posts, category )
  );
} );
