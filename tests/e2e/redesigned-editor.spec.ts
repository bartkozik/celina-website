import { expect, test } from '@playwright/test';
import {
  logIn,
  prepareEditor,
  currentEditorEntity,
  deleteEditorEntity,
  expectDraftSaved,
} from '../support/wordpress-editor';
import { runWithCleanup } from '../support/run-with-cleanup';

test( 'MR 73 key figures retain edited copy and icon after save/reload and render it publicly', async ( {
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
      await page.getByRole( 'tab', { name: 'Patterns', exact: true } ).click();
      await page
        .getByRole( 'searchbox', { name: 'Search', exact: true } )
        .fill( 'CELINA REDESIGNED — Homepage' );
      await page
        .getByText( 'CELINA REDESIGNED — Homepage', { exact: true } )
        .click();
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      const figure = canvas
        .locator( '[data-type="celina/achievement"]' )
        .first();
      const fields = {
        prefix: 'Since edited',
        year: '1997',
        label: 'test years',
        heading: 'Manufacturing test copy',
        content: 'Editable explanation from Gutenberg',
      };
      for ( const [ name, value ] of Object.entries( fields ) ) {
        if ( name === 'content' ) {
          await figure
            .getByRole( 'button', { name: 'Edit text', exact: true } )
            .click();
          const dialog = page.getByRole( 'dialog', {
            name: 'Edit Achievement description',
          } );
          await dialog
            .locator( '[data-type="core/paragraph"]' )
            .first()
            .fill( value );
          await dialog.getByRole( 'button', { name: 'Apply changes' } ).click();
        } else {
          await figure
            .locator( `.celina-achievement__${ name }` )
            .fill( value );
        }
      }
      await page
        .getByRole( 'combobox', { name: 'Icon', exact: true } )
        .selectOption( 'quality' );
      await page
        .getByRole( 'button', { name: 'Save draft', exact: true } )
        .click();
      await expectDraftSaved( page );
      await page.reload();
      await prepareEditor( page );
      for ( const [ name, value ] of Object.entries( fields ) ) {
        await expect(
          figure.locator( `.celina-achievement__${ name }` )
        ).toHaveText( value );
      }
      await expect(
        figure.locator( '.celina-achievement__icon--quality' )
      ).toHaveCount( 1 );
      await expect(
        canvas.getByText( 'This block contains unexpected or invalid content.' )
      ).toHaveCount( 0 );
      const response = await page.request.get(
        `/?page_id=${ entity.id }&preview=true`
      );
      expect( response.ok() ).toBeTruthy();
      const html = await response.text();
      for ( const value of Object.values( fields ) ) {
        expect( html ).toContain( value );
      }
      expect( html ).toContain( 'celina-achievement__icon--quality' );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );
