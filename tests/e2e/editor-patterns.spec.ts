import { readdirSync, readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { runWithCleanup } from '../support/run-with-cleanup';
import { blockMenuAction, expectEditedContent } from '../support/block-actions';
import {
  currentEditorEntity,
  deleteEditorEntity,
  expectDraftSaved,
  logIn,
  prepareEditor,
} from '../support/wordpress-editor';

const patternDirectory = 'wordpress/themes/celina-child/patterns';
const patternTitles = readdirSync( patternDirectory )
  .filter( ( file ) => file.startsWith( 'sales-' ) && file.endsWith( '.php' ) )
  .map( ( file ) => {
    const source = readFileSync( `${ patternDirectory }/${ file }`, 'utf8' );
    const title = source.match( /Title:\s*(.+)/ )?.[ 1 ]?.trim();
    if ( ! title ) {
      throw new Error( `Pattern title missing from ${ file }` );
    }
    return title;
  } )
  .flatMap( ( title ) => [
    title,
    title.replace( 'CELINA Sales', 'CELINA REDESIGNED' ),
  ] );

for ( const title of patternTitles ) {
  test( `${ title } can be inserted, edited and reloaded as native blocks`, async ( {
    page,
  } ) => {
    await logIn( page );
    await page.goto( '/wp-admin/post-new.php?post_type=page' );
    await prepareEditor( page );
    const entity = await currentEditorEntity( page );
    await runWithCleanup(
      async () => {
        await page
          .getByRole( 'button', { name: 'Block Inserter', exact: true } )
          .click();
        await page
          .getByRole( 'tab', { name: 'Patterns', exact: true } )
          .click();
        await page
          .getByRole( 'searchbox', { name: 'Search', exact: true } )
          .fill( title );
        await page.getByText( title, { exact: true } ).click();
        const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
        const text = canvas
          .locator( '[data-block] [contenteditable="true"]' )
          .first();
        await expect( text ).toBeVisible();
        await text.click( { timeout: 5000 } );
        await text.fill( `Edited ${ title }` );
        await text.press( 'Tab' );
        await expectEditedContent( page, `Edited ${ title }` );
        // Duplicate, move and remove the whole named section through native controls.
        const root = canvas
          .locator( '.is-root-container > [data-block]' )
          .first();
        const originalId = await root.getAttribute( 'data-block' );
        await blockMenuAction( page, root, 'Duplicate' );
        const roots = canvas.locator( '.is-root-container > [data-block]' );
        await expect( roots ).toHaveCount( 2 );
        await blockMenuAction( page, roots.nth( 1 ), 'Move up' );
        await expect( roots.nth( 1 ) ).toHaveAttribute(
          'data-block',
          originalId!
        );
        await blockMenuAction( page, roots.nth( 0 ), 'Delete' );
        await expect( roots ).toHaveCount( 1 );
        await page.getByRole( 'button', { name: 'Save draft' } ).click();
        await expectDraftSaved( page );
        await page.reload();
        await prepareEditor( page );
        await expect(
          canvas.getByText( `Edited ${ title }`, { exact: true } )
        ).toBeVisible();
        await expect(
          canvas.getByText(
            'This block contains unexpected or invalid content.'
          )
        ).toHaveCount( 0 );
        await expect( canvas.locator( '[data-type="core/html"]' ) ).toHaveCount(
          0
        );
      },
      async () => deleteEditorEntity( page, entity )
    );
  } );
}

test( 'all four editorial starters insert inside Article Layout and retain editable content', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  await runWithCleanup(
    async () => {
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      await page
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();
      await page
        .getByRole( 'searchbox', { name: 'Search', exact: true } )
        .fill( 'CELINA Article Layout' );
      await page
        .locator( '.editor-block-list-item-celina-article-layout' )
        .click();
      await page
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();
      const article = canvas.locator( '[data-type="celina/article-layout"]' );
      for ( const [ title, className ] of [
        [ 'CELINA Pro tip', 'celina-editorial-tip' ],
        [ 'CELINA Inline CTA', 'celina-editorial-cta' ],
        [ 'CELINA Attributed quote', 'celina-editorial-quote' ],
        [ 'CELINA Author bio', 'celina-editorial-bio' ],
      ] as const ) {
        await article
          .locator(
            ':scope > .block-editor-inner-blocks > .block-editor-block-list__layout > .block-list-appender button'
          )
          .click();
        const search = page.getByRole( 'searchbox', {
          name: 'Search',
          exact: true,
        } );
        await search.fill( title );
        await page.getByRole( 'option', { name: new RegExp( title ) } ).click();
        const starter = article.locator( `.${ className }` );
        await expect( starter ).toBeVisible();
        await starter
          .locator( '[contenteditable="true"]' )
          .first()
          .fill( `Edited ${ title }` );
      }
      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      await page.reload();
      await prepareEditor( page );
      for ( const title of [
        'Pro tip',
        'Inline CTA',
        'Attributed quote',
        'Author bio',
      ] ) {
        await expect(
          article.getByText( `Edited CELINA ${ title }`, { exact: true } )
        ).toBeVisible();
      }
      await expect(
        canvas.getByText( 'This block contains unexpected or invalid content.' )
      ).toHaveCount( 0 );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );
