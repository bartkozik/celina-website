import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { severeAxeViolations } from '../support/accessibility';
import {
  currentEditorEntity,
  deleteEditorEntity,
  logIn,
  prepareEditor,
} from '../support/wordpress-editor';

const original =
  '<p>Opening paragraph.</p><p><strong>Our purpose:</strong></p><ul><li>First point.</li><li>Second point.</li><li>Third point.</li></ul>';

test( 'legacy orphan list items remain editable without losing formatting', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  const content = original.replace( '<ul>', '' ).replace( '</ul>', '' );
  try {
    await page.evaluate( ( html ) => {
      const wp = ( window as any ).wp;
      wp.data
        .dispatch( 'core/block-editor' )
        .resetBlocks( [
          wp.blocks.createBlock( 'celina/text-image-split', { content: html } ),
        ] );
    }, content );
    const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
    const block = canvas.locator( '[data-type="celina/text-image-split"]' );
    const open = () =>
      block.getByRole( 'button', { name: 'Edit text' } ).click();
    const dialog = page.getByRole( 'dialog', { name: 'Edit Section content' } );
    await open();
    await expect(
      dialog.getByRole( 'button', { name: 'Apply changes' } )
    ).toBeEnabled();
    await expect(
      dialog.locator( '[data-type="core/list-item"]' )
    ).toHaveCount( 3 );
    await dialog.getByRole( 'button', { name: 'Apply changes' } ).click();
    const savedContent = () =>
      page.evaluate(
        () =>
          ( window as any ).wp.data
            .select( 'core/block-editor' )
            .getBlocks()[ 0 ].attributes.content
      );
    expect( await savedContent() ).toBe( content );
    await open();
    await dialog.locator( '[data-type="core/paragraph"]' ).first().click();
    await page.keyboard.press( 'Home' );
    await page.keyboard.type( 'Updated ' );
    await dialog.getByRole( 'button', { name: 'Cancel', exact: true } ).click();
    expect( await savedContent() ).toBe( content );
    await open();
    await dialog.locator( '[data-type="core/paragraph"]' ).first().click();
    await page.keyboard.press( 'Home' );
    await page.keyboard.type( 'Updated ' );
    await dialog
      .getByRole( 'textbox', { name: 'List text', exact: true } )
      .last()
      .click();
    await page.keyboard.press( 'End' );
    await page.keyboard.press( 'Enter' );
    await page.keyboard.type( 'Fourth point.' );
    await dialog.getByRole( 'button', { name: 'Apply changes' } ).click();
    await page.evaluate( async () => {
      const editor = ( window as any ).wp.data.dispatch( 'core/editor' );
      editor.editPost( { title: 'Legacy list regression', status: 'draft' } );
      await editor.savePost();
    } );
    await page.reload();
    await prepareEditor( page );
    await expect( block.locator( 'ul > li' ) ).toHaveCount( 4 );
    await expect( block ).toContainText( 'Updated Opening paragraph.' );
    await expect( block.locator( 'strong' ) ).toHaveText( 'Our purpose:' );
    await open();
    await expect(
      dialog.locator( '[data-type="core/list-item"]' )
    ).toHaveCount( 4 );
    await dialog.getByRole( 'button', { name: 'Cancel', exact: true } ).click();
    await page.goto( `/?page_id=${ entity.id }&preview=true` );
    await expect(
      page.locator( '.celina-text-image-split__prose ul > li' )
    ).toHaveCount( 4 );
  } finally {
    await deleteEditorEntity( page, entity );
  }
} );

test( 'rich content preserves paragraphs and list items after editing and reloading', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  try {
    await page.evaluate( ( content ) => {
      const wp = ( window as any ).wp;
      wp.data.dispatch( 'core/block-editor' ).resetBlocks( [
        wp.blocks.createBlock( 'celina/text-image-split', {
          heading: 'Formatting regression',
          content,
        } ),
      ] );
    }, original );
    const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
    const block = canvas.locator( '[data-type="celina/text-image-split"]' );
    const edit = block.getByRole( 'button', {
      name: 'Edit text',
      exact: true,
    } );
    if ( await edit.count() ) {
      await edit.click();
      const dialog = page.getByRole( 'dialog', {
        name: 'Edit Section content',
      } );
      const paragraph = dialog
        .locator( '[data-type="core/paragraph"]' )
        .first();
      await paragraph.click();
      await page.keyboard.press( 'Home' );
      await page.keyboard.type( 'Updated ' );
      await dialog.getByRole( 'button', { name: 'Apply changes' } ).click();
    } else {
      // Exercise the legacy editor too: this fails before the fix because
      // editing an unrelated paragraph merges the three list items.
      const field = block.locator( '.celina-text-image-split__prose' );
      await field.click();
      await page.keyboard.press( 'ControlOrMeta+Home' );
      await page.keyboard.type( 'Updated ' );
    }
    await expect(
      block.locator( '.celina-text-image-split__prose li' )
    ).toHaveCount( 3 );
    await expect(
      block.locator( '.celina-text-image-split__prose p' )
    ).toHaveCount( 2 );
    await page.evaluate( async () => {
      const editor = ( window as any ).wp.data.dispatch( 'core/editor' );
      editor.editPost( { title: 'Formatting regression', status: 'draft' } );
      await editor.savePost();
    } );
    await page.reload();
    await prepareEditor( page );
    await expect(
      block.locator( '.celina-text-image-split__prose li' )
    ).toHaveCount( 3 );
    await expect(
      block.locator( '.celina-text-image-split__prose' )
    ).toContainText( 'Updated' );
    await expect(
      canvas.getByText( /unexpected or invalid content/i )
    ).toHaveCount( 0 );
    await page.goto( `/?page_id=${ entity.id }&preview=true` );
    await expect(
      page.locator( '.celina-text-image-split__prose li' )
    ).toHaveCount( 3 );
    await expect(
      page.locator( '.celina-text-image-split__prose strong' )
    ).toHaveText( 'Our purpose:' );
  } finally {
    await deleteEditorEntity( page, entity );
  }
} );

const fields = [
  [ 'text-image-split', 'content', '' ],
  [ 'intro-band', 'content', '' ],
  [ 'faq-item', 'answer', 'faq' ],
  [ 'capability-card', 'content', 'capability-cards' ],
  [ 'industry-card', 'description', 'industry-cards' ],
  [ 'facility-card', 'address', 'facility-cards' ],
  [ 'resource-item', 'description', 'resource-list' ],
  [ 'achievement', 'content', 'achievements-timeline' ],
  [ 'capability-cards', 'introduction', '' ],
  [ 'industry-cards', 'introduction', '' ],
  [ 'facility-cards', 'introduction', '' ],
  [ 'resource-list', 'introduction', '' ],
  [ 'achievements-timeline', 'introduction', '' ],
] as const;

for ( const [ slug, field, parent ] of fields ) {
  test( `rich content ${ slug }: cancel, format, save and reload`, async ( {
    page,
  } ) => {
    await logIn( page );
    await page.goto( '/wp-admin/post-new.php?post_type=page' );
    await prepareEditor( page );
    const entity = await currentEditorEntity( page );
    const content =
      original +
      '<ol start="3"><li>Ordered <em>point</em> with <a href="https://example.org/">link</a>.</li><li>Another point.<ul><li>Nested point.</li></ul></li></ol>';
    try {
      await page.evaluate(
        ( args ) => {
          const wp = ( window as any ).wp;
          const child = wp.blocks.createBlock( `celina/${ args.slug }`, {
            heading: 'Content QA',
            title: 'Content QA',
            question: 'Content QA?',
            [ args.field ]: args.content,
          } );
          const root = args.parent
            ? wp.blocks.createBlock( `celina/${ args.parent }`, {}, [ child ] )
            : child;
          wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ root ] );
        },
        { slug, field, parent, content }
      );
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      const block = canvas.locator( `[data-type="celina/${ slug }"]` );
      const fieldEditor = block
        .locator( '.celina-rich-content-editor' )
        .first();
      const edit = fieldEditor.getByRole( 'button', {
        name: 'Edit text',
        exact: true,
      } );
      await edit.click();
      const dialog = page.getByRole( 'dialog', { name: /^Edit / } );
      await dialog.getByRole( 'button', { name: 'Apply changes' } ).click();
      const untouched = await page.evaluate(
        ( args ) => {
          const root = ( window as any ).wp.data
            .select( 'core/block-editor' )
            .getBlocks()[ 0 ];
          return ( args.parent ? root.innerBlocks[ 0 ] : root ).attributes[
            args.field
          ];
        },
        { parent, field }
      );
      expect( untouched ).toBe( content );
      await edit.click();
      await expect(
        dialog.locator( '[data-type="core/list-item"]' )
      ).toHaveCount( 6 );
      await dialog
        .locator( '[data-type="core/paragraph"]' )
        .first()
        .fill( 'Cancelled text.' );
      await dialog
        .getByRole( 'button', { name: 'Cancel', exact: true } )
        .click();
      await expect( fieldEditor ).toContainText( 'Opening paragraph.' );
      await expect( fieldEditor ).not.toContainText( 'Cancelled text.' );
      await edit.click();
      await dialog
        .locator( '[data-type="core/paragraph"]' )
        .first()
        .fill( 'Changed paragraph.' );
      await dialog.locator( '[data-type="core/list-item"]' ).first().click();
      await page.keyboard.press( 'End' );
      await page.keyboard.press( 'Enter' );
      await page.keyboard.type( 'New bullet.' );
      await expect(
        dialog.locator( '[data-type="core/list-item"]' )
      ).toHaveCount( 7 );
      await dialog.getByRole( 'button', { name: 'Apply changes' } ).click();
      await expect( fieldEditor.locator( 'li' ) ).toHaveCount( 7 );
      await expect( fieldEditor.locator( 'ol' ) ).toHaveAttribute(
        'start',
        '3'
      );
      await expect( fieldEditor.locator( 'a' ) ).toHaveAttribute(
        'href',
        'https://example.org/'
      );
      await page.evaluate( async () => {
        const editor = ( window as any ).wp.data.dispatch( 'core/editor' );
        editor.editPost( { title: 'Formatting QA', status: 'draft' } );
        await editor.savePost();
      } );
      await page.reload();
      await prepareEditor( page );
      await expect(
        canvas.getByText( /unexpected or invalid content/i )
      ).toHaveCount( 0 );
      await expect( fieldEditor.locator( 'li' ) ).toHaveCount( 7 );
      await expect( fieldEditor ).toContainText( 'Changed paragraph.' );
      await expect( fieldEditor ).toContainText( 'New bullet.' );
      await edit.click();
      await expect(
        dialog.locator( '[data-type="core/list-item"]' )
      ).toHaveCount( 7 );
      await dialog
        .getByRole( 'button', { name: 'Cancel', exact: true } )
        .click();
      await page.goto( `/?page_id=${ entity.id }&preview=true` );
      const output = page.locator( `.celina-${ slug }` ).first();
      await expect( output ).toContainText( 'Changed paragraph.' );
      await expect( output ).toContainText( 'New bullet.' );
      await expect( output.locator( 'ol[start="3"]' ) ).toHaveCount( 1 );
    } finally {
      await deleteEditorEntity( page, entity );
    }
  } );
}

test( 'rich content undo stays inside the text editor', async ( { page } ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  try {
    await page.evaluate( ( content ) => {
      const wp = ( window as any ).wp;
      wp.data.dispatch( 'core/block-editor' ).resetBlocks( [
        wp.blocks.createBlock( 'celina/text-image-split', {
          heading: 'Undo QA',
          content,
        } ),
      ] );
    }, original );
    const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
    await canvas
      .getByRole( 'button', { name: 'Edit text', exact: true } )
      .click();
    const dialog = page.getByRole( 'dialog', { name: 'Edit Section content' } );
    const paragraph = dialog.locator( '[data-type="core/paragraph"]' ).first();
    await paragraph.fill( 'Undo this change.' );
    await page.keyboard.press( 'ControlOrMeta+z' );
    await expect( dialog ).toBeVisible();
    await expect( paragraph ).toHaveText( 'Opening paragraph.' );
    await page.keyboard.press( 'ControlOrMeta+Shift+z' );
    await expect( paragraph ).toHaveText( 'Undo this change.' );
    await paragraph.click();
    await dialog
      .getByRole( 'button', { name: 'Bulleted list', exact: true } )
      .click();
    await expect(
      dialog.locator( '[data-type="core/list-item"]' )
    ).toHaveCount( 4 );
    await dialog
      .getByRole( 'button', { name: 'Numbered list', exact: true } )
      .click();
    await expect( dialog.locator( 'ol[data-type="core/list"]' ) ).toHaveCount(
      1
    );
    await dialog
      .getByRole( 'button', { name: 'Paragraph', exact: true } )
      .click();
    await expect( paragraph ).toHaveText( 'Undo this change.' );
    await page.screenshot( { path: 'artifacts/rich-content-editor.png' } );
    await dialog.getByRole( 'button', { name: 'Apply changes' } ).focus();
    await page.keyboard.press( 'ControlOrMeta+z' );
    await expect( dialog.locator( 'ol[data-type="core/list"]' ) ).toHaveCount(
      1
    );
    await page.keyboard.press( 'ControlOrMeta+Shift+z' );
    await expect( paragraph ).toHaveText( 'Undo this change.' );
    const violations = await new AxeBuilder( { page } )
      .include( '.celina-rich-content-modal' )
      // WordPress core's paragraph edit component emits role="document"
      // with textbox-only aria-multiline/aria-readonly attributes. Scope
      // this check to our dialog/controls rather than patching core markup.
      .exclude(
        '.celina-rich-content-modal__canvas [data-type="core/paragraph"][role="document"]'
      )
      .analyze();
    expect( severeAxeViolations( violations ) ).toEqual( [] );
    await page.setViewportSize( { width: 390, height: 844 } );
    expect(
      await dialog.evaluate(
        ( element ) => element.scrollWidth <= element.clientWidth + 1
      )
    ).toBe( true );
    await page.screenshot( {
      path: 'artifacts/rich-content-editor-mobile.png',
    } );
    await page.setViewportSize( { width: 1280, height: 720 } );
    await dialog.getByRole( 'button', { name: 'Cancel', exact: true } ).click();
    await expect(
      canvas.locator( '[data-type="celina/text-image-split"]' )
    ).toHaveCount( 1 );
    await canvas.locator( '[data-type="celina/text-image-split"] h2' ).click();
    await page.keyboard.press( 'ControlOrMeta+Shift+d' );
    await expect(
      canvas.locator( '[data-type="celina/text-image-split"]' )
    ).toHaveCount( 2 );
  } finally {
    await deleteEditorEntity( page, entity );
  }
} );

test( 'rich content supports empty fields and protects unsupported HTML', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  try {
    await page.evaluate( () => {
      const wp = ( window as any ).wp;
      wp.data.dispatch( 'core/block-editor' ).resetBlocks( [
        wp.blocks.createBlock( 'celina/text-image-split', {
          heading: 'Empty field QA',
        } ),
      ] );
    } );
    const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
    const edit = canvas.getByRole( 'button', {
      name: 'Edit text',
      exact: true,
    } );
    const dialog = page.getByRole( 'dialog', { name: 'Edit Section content' } );
    await edit.click();
    await dialog
      .locator( '[data-type="core/paragraph"]' )
      .fill( 'First new item.' );
    await dialog
      .getByRole( 'button', { name: 'Bulleted list', exact: true } )
      .click();
    await dialog.getByRole( 'button', { name: 'Apply changes' } ).click();
    await expect(
      canvas.locator( '.celina-text-image-split__prose li' )
    ).toHaveText( 'First new item.' );

    const unsupported =
      '<table><tbody><tr><td>Keep this table.</td></tr></tbody></table>';
    await page.evaluate( ( content ) => {
      const wp = ( window as any ).wp;
      const block = wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ];
      wp.data
        .dispatch( 'core/block-editor' )
        .updateBlockAttributes( block.clientId, { content } );
    }, unsupported );
    await edit.click();
    await expect(
      dialog.getByRole( 'button', { name: 'Apply changes' } )
    ).toBeDisabled();
    await expect(
      dialog.getByText( /includes an unsupported block/ )
    ).toBeVisible();
    await dialog.getByRole( 'button', { name: 'Cancel', exact: true } ).click();
    await expect(
      canvas.locator( '.celina-text-image-split__prose td' )
    ).toHaveText( 'Keep this table.' );
  } finally {
    await deleteEditorEntity( page, entity );
  }
} );
