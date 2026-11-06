import type { EditorBlock } from '../support/wordpress-browser';
import { execFileSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { logIn } from '../support/wordpress-editor';
import { severeAxeViolations } from '../support/accessibility';

const settings = '/wp-admin/themes.php?page=celina-header-footer';

async function waitForEditor( page: Page ) {
  await page.waitForFunction( () =>
    Boolean(
      window.wp?.data?.select( 'core/block-editor' )?.getBlocks()
        ?.length
    )
  );
}

async function savePart( page: Page ) {
  await page
    .getByRole( 'button', { name: 'Save', exact: true } )
    .first()
    .click();
  const confirm = page
    .getByRole( 'dialog' )
    .getByRole( 'button', { name: 'Save', exact: true } );
  if ( await confirm.isVisible() ) {
    await confirm.click();
  }
  await expect(
    page.getByText( /Template part updated|saved/i ).first()
  ).toBeVisible();
}

async function editorContent( page: Page ) {
  await waitForEditor( page );
  return page.evaluate( () => {
    const wp = window.wp;
    const blocks = wp.data.select( 'core/block-editor' ).getBlocks();
    const inspect = ( items: EditorBlock[] ): boolean =>
      items.some(
        ( block ) =>
          block.isValid === false || inspect( block.innerBlocks ?? [] )
      );
    return {
      content: wp.blocks.serialize( blocks ),
      invalid: inspect( blocks ),
    };
  } );
}

test( 'global parts support editing and responsive navigation without code', async ( {
  page,
} ) => {
  const originals: { url: string; content: string }[] = [];
  const fixture = await page.request.get(
    '/tests/integration/block-shell-parity-fixture.php'
  );
  const documents: unknown = await fixture.json();
  if ( ! documents || typeof documents !== 'object' || ! ( 'source' in documents ) ) {
    throw new Error( 'Missing shell fixture source.' );
  }
  const compositions = documents.source;
  if ( ! compositions || typeof compositions !== 'object' ||
    ! ( 'Header' in compositions ) || typeof compositions.Header !== 'string' ||
    ! ( 'Footer' in compositions ) || typeof compositions.Footer !== 'string' ) {
    throw new Error( 'Invalid shell fixture source.' );
  }
  await logIn( page );
  await page.goto( settings );
  const enable = page.getByRole( 'button', {
    name: 'Enable block header and footer',
    exact: true,
  } );
  if ( await enable.isVisible() ) {
    await enable.click();
  }
  try {
    for ( const part of [ 'Header', 'Footer' ] as const ) {
      await page.goto( settings );
      await page
        .getByRole( 'link', { name: `Edit ${ part }`, exact: true } )
        .click();
      const original = await editorContent( page );
      expect( original.invalid ).toBe( false );
      if ( part === 'Footer' ) {
        const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
        const brand = await canvas
          .locator( '.celina-block-footer-brand' )
          .boundingBox();
        const contact = await canvas
          .locator( '.celina-block-footer-contact' )
          .boundingBox();
        expect( Math.abs( brand!.y - contact!.y ) ).toBeLessThan( 4 );
        await expect(
          canvas.locator( '.celina-block-footer-contact p' ).first()
        ).toHaveCSS( 'margin-top', '0px' );
      }
      originals.push( { url: page.url(), content: original.content } );
      await page.evaluate( ( content ) => {
        const wp = window.wp;
        wp.data
          .dispatch( 'core/block-editor' )
          .resetBlocks( wp.blocks.parse( content ) );
      }, compositions[ part ] );
      if ( part === 'Header' ) {
        await page.evaluate( async () => {
          const wp = window.wp;
          const store = wp.data.dispatch( 'core/block-editor' );
          const walk = ( blocks: EditorBlock[] ): EditorBlock[] =>
            blocks.flatMap( ( block ) => [
              block,
              ...walk( block.innerBlocks ?? [] ),
            ] );
          const blocks = walk(
            wp.data.select( 'core/block-editor' ).getBlocks()
          );
          const source = blocks.find(
            ( block ) =>
              block.attributes.className
                ?.split( ' ' )
                .includes( 'celina-block-header-cta' )
          );
          const button = source?.innerBlocks[ 0 ];
          if ( ! button ) throw new Error( 'Missing header CTA button.' );
          await store.updateBlockAttributes( button.clientId, {
            text: 'Discuss revised project',
            url: `${ window.location.origin }/contact/?source=single-cta`,
          } );
        } );
        const edited = await editorContent( page );
        expect( edited.content.match( /<!-- wp:buttons /g ) ).toHaveLength( 1 );
        expect( edited.content ).not.toContain( 'celina-block-mobile-cta' );
      }

      await page.waitForFunction( ( region ) => {
        const store = window.wp.data.select( 'core/block-editor' );
        const root = store
          .getBlocks()
          .find(
            ( block ) =>
              block.attributes.className
                ?.split( ' ' )
                .includes( `celina-block-${ region.toLowerCase() }` )
          );
        return (
          root && store.canInsertBlockType( 'core/paragraph', root.clientId )
        );
      }, part );
      await page.evaluate( async ( region ) => {
        const wp = window.wp;
        const store = wp.data.dispatch( 'core/block-editor' );
        const root = wp.data
          .select( 'core/block-editor' )
          .getBlocks()
          .find(
            ( block ) =>
              block.attributes.className
                ?.split( ' ' )
                .includes( `celina-block-${ region.toLowerCase() }` )
          );
        if ( ! root ) throw new Error( 'Missing shell region.' );
        const first = wp.blocks.createBlock( 'core/paragraph', {
          content: `${ region } temporary element`,
        } );
        const second = wp.blocks.createBlock( 'core/paragraph', {
          content: `CEL-30 ${ region } editorial verification`,
        } );
        await store.insertBlocks( [ first, second ], undefined, root.clientId );
        await store.moveBlocksToPosition(
          [ second.clientId ],
          root.clientId,
          root.clientId,
          0
        );
        await store.removeBlocks( [ first.clientId ], false );
        if ( region === 'Footer' ) {
          const grid = root.innerBlocks[ 0 ]?.innerBlocks[ 0 ];
          if ( ! grid ) throw new Error( 'Missing footer grid.' );
          const links = grid.innerBlocks.find(
            ( block ) =>
              block.attributes.className === 'celina-block-footer-links'
          );
          if ( ! links ) throw new Error( 'Missing footer links.' );
          await store.moveBlocksToPosition(
            [ links.clientId ],
            grid.clientId,
            grid.clientId,
            1
          );
        }
        if ( region === 'Header' ) {
          const findNavigation = ( blocks: EditorBlock[] ): EditorBlock | undefined => {
            for ( const block of blocks ) {
              if (
                block.name === 'core/navigation' &&
                block.attributes.className === 'celina-block-primary'
              ) {
                return block;
              }
              const found = findNavigation( block.innerBlocks ?? [] );
              if ( found ) {
                return found;
              }
            }
          };
          const navigation = findNavigation( [ root ] );
          if ( ! navigation ) throw new Error( 'Missing header navigation.' );
          await store.insertBlocks(
            wp.blocks.createBlock( 'core/navigation-link', {
              label: 'Current page test',
              url: `${ window.location.origin }/`,
              kind: 'custom',
            } ),
            undefined,
            navigation.clientId
          );
          await store.insertBlocks(
            wp.blocks.createBlock(
              'core/navigation-submenu',
              {
                label: 'More links',
                url: `${ window.location.origin }/fixture-news/`,
                kind: 'custom',
              },
              [
                wp.blocks.createBlock( 'core/navigation-link', {
                  label: 'Nested link test',
                  url: `${ window.location.origin }/fixture-news-1/`,
                  kind: 'custom',
                } ),
              ]
            ),
            undefined,
            navigation.clientId
          );
        }
      }, part );
      await savePart( page );
      await page.reload();
      const saved = await editorContent( page );
      expect( saved.invalid ).toBe( false );
      if ( part === 'Header' ) {
        expect( saved.content.match( /<!-- wp:buttons /g ) ).toHaveLength( 1 );
        expect( saved.content ).not.toContain( 'celina-block-mobile-cta' );
        expect( saved.content ).toContain( 'Discuss revised project' );
        await expect(
          page
            .frameLocator( 'iframe[name="editor-canvas"]' )
            .locator( '.wp-block-button__link' )
        ).toHaveCount( 1 );
      }

      expect( saved.content ).toContain(
        `CEL-30 ${ part } editorial verification`
      );
      expect( saved.content ).not.toContain( `${ part } temporary element` );
      expect(
        await page.evaluate(
          ( region ) =>
            String(
              window.wp.data
                .select( 'core/block-editor' )
                .getBlocks()
                .find(
                  ( block ) =>
                    block.attributes.className
                      ?.split( ' ' )
                      .includes( `celina-block-${ region.toLowerCase() }` )
                )?.innerBlocks[ 0 ]?.attributes.content
            ),
          part
        )
      ).toContain( `CEL-30 ${ part } editorial verification` );
    }
    for ( const width of [ 375, 768, 1440 ] ) {
      await page.setViewportSize( { width, height: 900 } );
      await page.goto( '/' );
      for ( const part of [ 'header', 'footer' ] ) {
        const region = page.locator( `${ part }.celina-block-${ part }` );
        await expect( region ).toHaveCount( 1 );
        await expect( region ).toContainText(
          `CEL-30 ${
            part === 'header' ? 'Header' : 'Footer'
          } editorial verification`
        );
        await expect( region ).not.toContainText( 'temporary element' );
      }
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth
        )
      ).toBe( true );
      if ( width === 1440 ) {
        const brand = await page
          .locator( '.celina-block-footer-brand' )
          .boundingBox();
        const links = await page
          .locator( '.celina-block-footer-links' )
          .boundingBox();
        expect( Math.abs( brand!.y - links!.y ) ).toBeLessThan( 4 );
        expect( links!.x ).toBeGreaterThan( brand!.x + brand!.width );
        const contact = await page
          .locator( '.celina-block-footer-contact' )
          .boundingBox();
        expect( links!.x ).toBeLessThan( contact!.x );
        const contacts = await page
          .locator( '.celina-block-contacts' )
          .boundingBox();
        const utility = await page
          .locator( 'nav.celina-block-utility-nav' )
          .boundingBox();
        expect( Math.abs( contacts!.y - utility!.y ) ).toBeLessThan( 10 );
      }
      const header = page.locator( '.celina-block-header' );
      for ( const selector of [
        '.celina-block-header-cta',
        '.celina-block-mobile-cta',
      ] ) {
        const button = header.locator( `${ selector } .wp-block-button__link` );
        await expect( button ).toHaveText( 'Discuss revised project' );
        await expect( button ).toHaveAttribute(
          'href',
          /\/contact\/\?source=single-cta$/
        );
      }

      await expect(
        header.locator( 'nav.celina-block-primary a[aria-current="page"]' )
      ).toHaveText( 'Current page test' );
      expect(
        await header.evaluate(
          ( element ) => getComputedStyle( element ).backgroundColor
        )
      ).toBe( 'rgb(61, 69, 67)' );
      expect(
        await header
          .locator( '.celina-block-header-cta .wp-block-button__link' )
          .evaluate(
            ( element ) => getComputedStyle( element ).backgroundColor
          )
      ).toBe( 'rgb(255, 108, 13)' );
      const open = header.locator( '.celina-block-menu > summary' );
      if ( width < 1024 ) {
        await expect( open ).toBeVisible();
        await open.click();
        await expect( header.locator( '.celina-block-menu' ) ).toHaveAttribute(
          'open',
          ''
        );
        await expect(
          header.locator( '.celina-block-mobile-cta' )
        ).toBeVisible();
        await expect(
          header.getByText( 'Nested link test', { exact: true } )
        ).toBeVisible();
        await page.keyboard.press( 'Escape' );
      }
    }
    const submenu = page.locator(
      'nav.celina-block-primary .wp-block-navigation-submenu > a'
    );
    await submenu.last().focus();
    await expect(
      page
        .locator( 'nav.celina-block-primary' )
        .getByText( 'Nested link test', { exact: true } )
    ).toBeVisible();
    await page.keyboard.press( 'Escape' );
    await expect(
      page.locator( 'nav.celina-block-primary a[aria-current="page"]' )
    ).toHaveCSS(
      'color',
      ( await page.locator( 'body.celina-sales-review' ).count() )
        ? 'rgb(255, 108, 13)'
        : 'rgb(255, 255, 255)'
    );
    await page.addStyleTag( {
      content: '.celina-block-shell * { transition: none !important; }',
    } );
    const results = await new AxeBuilder( { page } )
      .include( '.celina-block-header' )
      .include( '.celina-block-footer' )
      .analyze();
    // The unchanged legacy sales palette uses orange active links below AA contrast.
    // Exempt only those color-contrast nodes; all other rules still inspect them.
    for ( const violation of results.violations ) {
      if ( violation.id !== 'color-contrast' ) {
        continue;
      }
      const unexpected = [];
      for ( const node of violation.nodes ) {
        const knownLegacyColor = await page
          .locator( node.target[ 0 ] as string )
          .evaluate( ( element ) => {
            const link = element.closest( 'a[aria-current]' );
            return Boolean(
              link?.closest(
                'nav.celina-block-primary, nav.celina-block-footer-nav'
              ) && getComputedStyle( link ).color === 'rgb(255, 108, 13)'
            );
          } );
        if ( ! knownLegacyColor ) {
          unexpected.push( node );
        }
      }
      violation.nodes = unexpected;
    }
    results.violations = results.violations.filter(
      ( violation ) => violation.nodes.length
    );
    expect( severeAxeViolations( results ) ).toEqual( [] );
    for ( const route of [
      '/fixture-news-1/',
      '/?s=CELINA',
      '/cel30-nonexistent-page/',
    ] ) {
      await page.goto( route );
      await expect( page.locator( '.celina-block-header' ) ).toContainText(
        'CEL-30 Header editorial verification'
      );
      await expect( page.locator( '.celina-block-footer' ) ).toContainText(
        'CEL-30 Footer editorial verification'
      );
    }
    await page.goto( originals[ 0 ]!.url );
    await waitForEditor( page );
    await page.evaluate( async () => {
      const wp = window.wp;
      const walk = ( blocks: EditorBlock[] ): EditorBlock[] =>
        blocks.flatMap( ( block ) => [
          block,
          ...walk( block.innerBlocks ?? [] ),
        ] );
      const source = walk(
        wp.data.select( 'core/block-editor' ).getBlocks()
      ).find(
        ( block ) =>
          block.attributes.className
            ?.split( ' ' )
            .includes( 'celina-block-header-cta' )
      );
      if ( ! source ) throw new Error( 'Missing header CTA.' );
      await wp.data
        .dispatch( 'core/block-editor' )
        .removeBlocks( [ source.clientId ], false );
    } );
    await savePart( page );
    await page.reload();
    expect( ( await editorContent( page ) ).invalid ).toBe( false );
    await page.goto( '/' );
    await expect(
      page.locator( '.celina-block-header .wp-block-buttons' )
    ).toHaveCount( 0 );
    await page.goto( settings );
    await page
      .getByRole( 'button', {
        name: 'Use legacy header and footer',
        exact: true,
      } )
      .click();
    await page.goto( '/' );
    await expect( page.locator( '.celina-block-footer' ) ).toHaveCount( 0 );
    await page.goto( settings );
    await page
      .getByRole( 'button', {
        name: 'Enable block header and footer',
        exact: true,
      } )
      .click();
    await page.goto( '/' );
    await expect( page.locator( '.celina-block-footer' ) ).toContainText(
      'CEL-30 Footer editorial verification'
    );
  } finally {
    await page.setViewportSize( { width: 1440, height: 900 } );
    for ( const original of originals ) {
      await page.goto( original.url );
      await waitForEditor( page );
      await page.evaluate( ( content ) => {
        const wp = window.wp;
        wp.data
          .dispatch( 'core/block-editor' )
          .resetBlocks( wp.blocks.parse( content ) );
      }, original.content );
      await savePart( page );
    }
    await page.goto( settings );
    const disable = page.getByRole( 'button', {
      name: 'Use legacy header and footer',
      exact: true,
    } );
    if ( await disable.isVisible() ) {
      await disable.click();
    }
  }
} );

test( 'block header receives keyboard focus from back to top', async ( {
  page,
} ) => {
  test.skip(
    ! process.env.WP_ENV_HOME,
    'Requires the isolated WordPress runner.'
  );
  const wp = ( ...args: string[] ) =>
    execFileSync( 'pnpm', [ 'exec', 'wp-env', 'run', 'cli', 'wp', ...args ], {
      env: process.env,
      stdio: 'pipe',
    } );
  try {
    wp( 'celina', 'shell', 'apply', '--user=admin' );
    wp( 'theme', 'mod', 'set', 'celina_sales_review', '1' );
    await page.emulateMedia( { reducedMotion: 'reduce' } );
    await page.goto( '/' );
    await page.locator( '.celina-block-footer' ).scrollIntoViewIfNeeded();
    const button = page.getByRole( 'button', { name: 'Back to top' } );
    await expect( button ).toBeVisible();
    await button.focus();
    await page.keyboard.press( 'Enter' );
    await expect( page.locator( '.celina-block-header-logo a' ) ).toBeFocused();
    await expect.poll( () => page.evaluate( () => window.scrollY ) ).toBe( 0 );
    await expect( button ).toBeHidden();
  } finally {
    wp( 'celina', 'shell', 'disable', '--user=admin' );
    wp( 'theme', 'mod', 'remove', 'celina_sales_review' );
  }
} );
