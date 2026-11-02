import { expect, type Locator, type Page } from '@playwright/test';

export async function blockMenuAction(
  page: Page,
  block: Locator,
  action: string
) {
  // A section taller than the canvas pins its toolbar under the editor top
  // bar. Keep the block's top edge in view so the native toolbar is reachable.
  await block.evaluate( ( element ) => {
    element.scrollIntoView( { block: 'start' } );
    element.ownerDocument.defaultView?.scrollBy( 0, -120 );
  } );
  await block.focus();
  await block.press( 'Escape' );
  if ( action === 'Move up' || action === 'Move down' ) {
    await page
      .getByRole( 'toolbar', { name: 'Block tools' } )
      .getByRole( 'button', { name: action, exact: true } )
      .click( { timeout: 5000 } );
    return;
  }
  await page
    .getByRole( 'toolbar', { name: 'Block tools' } )
    .getByRole( 'button', { name: 'Options', exact: true } )
    .click( { timeout: 5000 } );
  await page
    .getByRole( 'menuitem', { name: new RegExp( `^${ action }(?:$| )` ) } )
    .click( { timeout: 5000 } );
}

export async function expectEditedContent( page: Page, text: string ) {
  await expect
    .poll( () =>
      page.evaluate( () => {
        const { wp } = window as typeof window & {
          wp: {
            data: {
              select: ( store: string ) => {
                getEditedPostContent: () => string;
              };
            };
          };
        };
        return wp.data.select( 'core/editor' ).getEditedPostContent();
      } )
    )
    .toContain( text );
}
