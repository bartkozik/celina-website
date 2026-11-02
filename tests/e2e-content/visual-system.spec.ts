import { expect, test } from '@playwright/test';

test.describe( 'CELINA visual system', () => {
  test.skip(
    Boolean( process.env.CELINA_CONTENT_GROUP ),
    'The visual-system suite requires the complete managed-content set.'
  );

  test( 'uses the brand shell and full-width landing sections', async ( {
    page,
  } ) => {
    await page.setViewportSize( { height: 900, width: 1440 } );
    await page.goto( '/home/', { waitUntil: 'domcontentloaded' } );

    const headerStyles = await page
      .locator( '.celina-header' )
      .evaluate( ( el ) => {
        const styles = getComputedStyle( el );
        return {
          backgroundColor: styles.backgroundColor,
          borderBottomWidth: styles.borderBottomWidth,
          position: styles.position,
        };
      } );
    expect( headerStyles ).toEqual( {
      backgroundColor: 'rgb(61, 69, 67)',
      // The orange rule under the menu was removed in the 2026-09-22 rework.
      borderBottomWidth: '0px',
      position: 'sticky',
    } );

    const hero = page.locator( '.wp-block-celina-hero' );
    const heroBox = await hero.boundingBox();
    const heroStyles = await hero.evaluate( ( el ) => {
      const styles = getComputedStyle( el );
      return { alignItems: styles.alignItems, minHeight: styles.minHeight };
    } );
    const headingStyles = await hero.locator( 'h1' ).evaluate( ( el ) => {
      const styles = getComputedStyle( el );
      return {
        fontSize: Number.parseFloat( styles.fontSize ),
        fontWeight: Number.parseInt( styles.fontWeight, 10 ),
      };
    } );
    expect( heroBox?.width ).toBeGreaterThanOrEqual( 1439 );
    // Iza's shared desktop hero token is 31rem at this viewport.
    expect( heroStyles.minHeight ).toBe( '496px' );
    expect( heroStyles.alignItems ).toBe( 'center' );
    expect( headingStyles.fontSize ).toBeGreaterThanOrEqual( 44 );
    expect( headingStyles.fontWeight ).toBeGreaterThanOrEqual( 800 );

    const section = page.locator( '.wp-block-celina-industry-cards' ).first();
    const sectionBox = await section.boundingBox();
    const cardRadius = await section
      .locator( '.wp-block-celina-industry-card' )
      .first()
      .evaluate( ( el ) => getComputedStyle( el ).borderRadius );
    expect( sectionBox?.width ).toBeGreaterThanOrEqual( 1439 );
    expect( cardRadius ).toBe( '2px' );

    // Header, hero content and card grid share one left edge.
    const leftEdge = ( selector: string ) =>
      page
        .locator( selector )
        .first()
        .evaluate( ( el ) => Math.round( el.getBoundingClientRect().left ) );
    const headerEdge = await leftEdge( '.celina-header .celina-shell-inner' );
    expect( await leftEdge( '.celina-hero__inner' ) ).toBe( headerEdge );
    expect( await leftEdge( '.celina-industry-cards__inner' ) ).toBe(
      headerEdge
    );
  } );

  test( 'collapses collections and preserves the article measure', async ( {
    page,
  } ) => {
    await page.setViewportSize( { height: 812, width: 375 } );
    await page.goto( '/home/', { waitUntil: 'domcontentloaded' } );

    const grid = page.locator( '.celina-industry-cards__grid' ).first();
    const columns = await grid.evaluate(
      ( el ) => getComputedStyle( el ).gridTemplateColumns
    );
    expect( columns.trim().split( /\s+/ ) ).toHaveLength( 1 );

    await page.setViewportSize( { height: 900, width: 1440 } );
    await page.goto( '/thirty-years-built-by-people/', {
      waitUntil: 'domcontentloaded',
    } );
    const articleBox = await page
      .locator( '.celina-single-article' )
      .boundingBox();
    expect( articleBox?.width ).toBeLessThanOrEqual( 760 );
  } );

  test( 'uses the reviewed two-to-one Contact layout on desktop', async ( {
    page,
  } ) => {
    await page.setViewportSize( { height: 900, width: 1440 } );
    await page.goto( '/contact/', { waitUntil: 'domcontentloaded' } );

    const layout = await page
      .locator( '.celina-contact-columns' )
      .evaluate( ( el ) => ( {
        display: getComputedStyle( el ).display,
        widths: Array.from(
          el.children,
          ( child ) => child.getBoundingClientRect().width
        ),
      } ) );

    expect( layout.display ).toBe( 'grid' );
    expect( layout.widths ).toHaveLength( 2 );
    expect(
      ( layout.widths[ 0 ] ?? 0 ) / ( layout.widths[ 1 ] ?? 1 )
    ).toBeCloseTo( 2, 3 );
  } );

  test( 'stacks the Contact layout on mobile', async ( { page } ) => {
    await page.setViewportSize( { height: 812, width: 375 } );
    await page.goto( '/contact/', { waitUntil: 'domcontentloaded' } );

    const columns = await page
      .locator( '.celina-contact-columns' )
      .evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns );

    expect( columns.trim().split( /\s+/ ) ).toHaveLength( 1 );
  } );
} );
