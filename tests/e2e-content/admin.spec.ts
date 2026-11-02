import { expect, test } from '@playwright/test';
import { logIn, prepareEditor } from '../support/wordpress-editor';

test( 'all ten active redesigned pages are labeled and open as editable native blocks', async ( {
  page,
} ) => {
  test.skip(
    Boolean( process.env.CELINA_CONTENT_GROUP ),
    'Requires all managed pages.'
  );
  await logIn( page );
  for ( const title of [
    'Home',
    'About CELINA',
    'Capabilities',
    'CELINA Industries',
    'Why CELINA',
    'Community',
    'Careers',
    'Contact',
    'Corporate News',
    'Corporate Resources',
  ] ) {
    await page.goto( '/wp-admin/edit.php?post_type=page' );
    const row = page.locator( '#the-list tr' ).filter( {
      has: page.getByRole( 'link', { name: title, exact: true } ),
    } );
    await expect(
      row.getByText( '(redesigned)', { exact: true } )
    ).toBeVisible();
    await row.getByRole( 'link', { name: title, exact: true } ).click();
    await prepareEditor( page );
    const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
    await expect( canvas.locator( '.celina-sales-page' ) ).toHaveCount( 1 );
    await expect(
      canvas.locator( '[data-block] [contenteditable="true"]' ).first()
    ).toBeVisible();
    await expect(
      canvas.getByText( 'This block contains unexpected or invalid content.' )
    ).toHaveCount( 0 );
    await expect( canvas.locator( '[data-type="core/html"]' ) ).toHaveCount(
      0
    );
  }
} );
