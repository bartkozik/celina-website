import type { EditorBlock } from '../support/wordpress-browser';
import { expect, test } from '@playwright/test';
import {
  currentEditorEntity,
  deleteEditorEntity,
  logIn,
  prepareEditor,
} from '../support/wordpress-editor';

test( 'native gallery retains every existing portrait when adding, replacing and reordering photos', async ( {
  page,
} ) => {
  test.skip(
    Boolean( process.env.CELINA_CONTENT_GROUP ),
    'Requires migrated whole-site media.'
  );
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  try {
    const original = await page.evaluate( async () => {
      const wp = window.wp;
      const patterns = await wp.apiFetch( {
        path: '/wp/v2/block-patterns/patterns',
      } );
      const pattern = patterns.find(
        ( item ) => item.name === 'celina/redesigned-page-about'
      );
      const flatten = ( blocks: EditorBlock[] ): EditorBlock[] =>
        blocks.flatMap( ( block ) => [
          block,
          ...flatten( block.innerBlocks ),
        ] );
      if ( ! pattern ) throw new Error( 'Missing About pattern.' );
      const gallery = flatten( wp.blocks.parse( pattern.content ) ).find(
        ( block ) => block.name === 'core/gallery'
      );
      if ( ! gallery ) throw new Error( 'Missing gallery.' );
      wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ gallery ] );
      wp.data.dispatch( 'core/block-editor' ).selectBlock( gallery.clientId );
      return gallery.innerBlocks.map( ( block ) => ( {
        id: block.attributes.id,
        caption: block.attributes.caption,
      } ) );
    } );
    expect( original ).toHaveLength( 7 );
    expect( original.every( ( photo ) => typeof photo.id === 'number' && photo.id > 0 ) ).toBe( true );
    await page.getByRole( 'button', { name: 'Add', exact: true } ).click();
    await page.getByRole( 'menuitem', { name: 'Open Media Library' } ).click();
    const dialog = page.getByRole( 'dialog' );
    await dialog
      .locator( '.media-menu-item' )
      .filter( { hasText: /^Edit gallery$/ } )
      .click();
    await expect( dialog.locator( '.attachments .attachment' ) ).toHaveCount(
      7
    );
    await dialog
      .locator( '.media-menu-item' )
      .filter( { hasText: /^Add to gallery$/ } )
      .click();
    await dialog
      .getByRole( 'tab', { name: 'Media Library', exact: true } )
      .click();
    const additional = dialog.locator( '.attachments .attachment' ).first();
    await additional.click();
    await dialog
      .getByRole( 'button', { name: 'Add to gallery', exact: true } )
      .click();
    await expect( dialog.locator( '.attachments .attachment' ) ).toHaveCount(
      8
    );
    await dialog
      .getByRole( 'button', { name: 'Update gallery', exact: true } )
      .click();
    const readImages = () =>
      page.evaluate( () => {
        const gallery = window.wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ];
        if ( ! gallery ) throw new Error( 'Missing gallery.' );
        return gallery.innerBlocks.map( ( block ) => ( {
          id: block.attributes.id,
          caption: block.attributes.caption,
        } ) );
      } );
    expect( ( await readImages() ).slice( 0, 7 ) ).toEqual( original );
    const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
    await expect( canvas.locator( '[data-type="core/image"]' ) ).toHaveCount(
      8
    );
    await page.evaluate( () => {
      const wp = window.wp;
      const gallery = wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ];
      const image = gallery?.innerBlocks[ 7 ];
      if ( ! image ) throw new Error( 'Missing eighth gallery image.' );
      wp.data.dispatch( 'core/block-editor' ).selectBlock( image.clientId );
    } );
    await page.getByRole( 'button', { name: 'Replace', exact: true } ).click();
    await page.getByRole( 'menuitem', { name: 'Open Media Library' } ).click();
    await dialog
      .getByRole( 'tab', { name: 'Media Library', exact: true } )
      .click();
    await dialog.locator( '.attachments .attachment' ).nth( 1 ).click();
    await dialog.getByRole( 'button', { name: 'Select', exact: true } ).click();
    await page
      .getByRole( 'button', { name: 'Move left', exact: true } )
      .click();
    const edited = await readImages();
    expect( edited[ 7 ] ).toEqual( original[ 6 ] );
    await page.evaluate( async () => {
      const editor = window.wp.data.dispatch( 'core/editor' );
      editor.editPost( { title: 'Media editor regression', status: 'draft' } );
      await editor.savePost();
    } );
    await page.reload();
    await prepareEditor( page );
    expect( await readImages() ).toEqual( edited );
    await expect(
      canvas.getByText( 'This block contains unexpected or invalid content.' )
    ).toHaveCount( 0 );
    await page.goto( `/?page_id=${ entity.id }&preview=true` );
    await expect( page.locator( '.wp-block-gallery img' ) ).toHaveCount( 8 );
    for ( const image of await page.locator( '.wp-block-gallery img' ).all() ) {
      await image.scrollIntoViewIfNeeded();
      await expect
        .poll( () =>
          image.evaluate(
            ( element: HTMLImageElement ) =>
              element.complete && element.naturalWidth > 0
          )
        )
        .toBe( true );
    }
  } finally {
    await deleteEditorEntity( page, entity );
  }
} );

test( 'migrated hero and contact backgrounds can be removed without restoring CSS photographs', async ( {
  page,
} ) => {
  test.skip(
    Boolean( process.env.CELINA_CONTENT_GROUP ),
    'Requires migrated whole-site media.'
  );
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );
  try {
    await page.evaluate( async () => {
      const wp = window.wp;
      const patterns = await wp.apiFetch( {
        path: '/wp/v2/block-patterns/patterns',
      } );
      const flatten = ( blocks: EditorBlock[] ): EditorBlock[] =>
        blocks.flatMap( ( block ) => [
          block,
          ...flatten( block.innerBlocks ),
        ] );
      const blocks = ( name: string ) => {
        const pattern = patterns.find( ( item ) => item.name === name );
        if ( ! pattern ) throw new Error( `Missing pattern: ${ name }` );
        return flatten( wp.blocks.parse( pattern.content ) );
      };
      const cover = blocks( 'celina/sales-contact-heading' ).find(
        ( block ) => block.name === 'core/cover'
      );
      const hero = blocks( 'celina/redesigned-page-about' ).find(
        ( block ) => block.name === 'celina/hero'
      );
      if ( ! cover || ! hero ) throw new Error( 'Missing cover or hero block.' );
      wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ cover, hero ] );
      wp.data.dispatch( 'core/block-editor' ).selectBlock( cover.clientId );
    } );
    const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
    await page.getByRole( 'button', { name: 'Replace', exact: true } ).click();
    await page.getByRole( 'menuitem', { name: 'Reset', exact: true } ).click();
    await expect(
      canvas.locator( '.wp-block-cover__image-background' )
    ).toHaveCount( 0 );
    await page.evaluate( () => {
      const wp = window.wp;
      const hero = wp.data.select( 'core/block-editor' ).getBlocks()[ 1 ];
      if ( ! hero ) throw new Error( 'Missing hero.' );
      wp.data.dispatch( 'core/block-editor' ).selectBlock( hero.clientId );
    } );
    await page
      .getByRole( 'button', { name: 'Remove background image', exact: true } )
      .click();
    await page.evaluate( async () => {
      const editor = window.wp.data.dispatch( 'core/editor' );
      editor.editPost( {
        title: 'Media background regression',
        status: 'draft',
      } );
      await editor.savePost();
    } );
    await page.reload();
    await prepareEditor( page );
    await expect(
      canvas.getByText( 'This block contains unexpected or invalid content.' )
    ).toHaveCount( 0 );
    await expect(
      canvas.locator( '.wp-block-cover__image-background' )
    ).toHaveCount( 0 );
    await expect( canvas.locator( '.celina-hero' ) ).toHaveCSS(
      'background-image',
      'none'
    );
    await page.goto( `/?page_id=${ entity.id }&preview=true` );
    await expect( page.locator( '.celina-sales-contact-heading' ) ).toHaveCSS(
      'background-image',
      'none'
    );
    await expect( page.locator( '.celina-hero' ) ).toHaveCSS(
      'background-image',
      'none'
    );
    await expect(
      page.locator( '.wp-block-cover__image-background' )
    ).toHaveCount( 0 );
  } finally {
    await deleteEditorEntity( page, entity );
  }
} );
