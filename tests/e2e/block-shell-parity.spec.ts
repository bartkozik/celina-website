import { expect, test } from '@playwright/test';

type Variant = 'legacy' | 'blocks';
type Geometry = { x: number; y: number; width: number; height: number };
type Surface = Geometry & {
  color: string;
  background: string;
  fontSize: string;
};
type NavigationLink = Geometry & {
  text: string | undefined;
  font: string;
  color: string;
};
type FooterLink = Geometry & {
  href: string;
  text: string | undefined;
  color: string;
  weight: string;
};
type DropdownSurface = Geometry & {
  color: string;
  background: string;
  shadow: string;
  border: string;
};
type Dropdown = { menu: DropdownSurface; links: DropdownSurface[] };
const variants = [ 'legacy', 'blocks' ] as const;
const dimensions = [ 'x', 'y', 'width', 'height' ] as const;

for ( const sales of [ true, false ] ) {
  for ( const emptyMenu of [ false, true ] ) {
    test( `block shell retains legacy dimensions and disclosure (sales ${ sales }, assigned empty menu ${ emptyMenu })`, async ( {
      page,
    } ) => {
      const response = await page.request.get(
        '/tests/integration/block-shell-parity-fixture.php' +
          `?${ new URLSearchParams( {
            ...( sales ? {} : { legacy_style: '1' } ),
            ...( emptyMenu ? { empty_menu: '1' } : {} ),
          } ) }`
      );
      const documents = parseDocuments( await response.json() );
      const pairs: [ string, string ][] = [
        [ '.celina-utility', '.celina-block-utility' ],
        [ '.celina-header', '.celina-block-header' ],
        [
          '.celina-header-cta',
          '.celina-block-header-cta .wp-block-button__link',
        ],
        [ '.celina-footer', '.celina-block-footer' ],
        [ '.celina-footer-logo', '.celina-block-footer-logo' ],
        [ '.celina-footer-tagline', '.celina-block-footer-tagline' ],
        [
          '.celina-footer-column:nth-of-type(2)',
          '.celina-block-footer-links',
        ],
        [
          '.celina-footer-column:nth-of-type(3)',
          '.celina-block-footer-contact',
        ],
        [ '.celina-footer-meta', '.celina-block-footer-meta' ],
      ];
      for ( const width of [ 1440, 768, 375 ] ) {
        await page.setViewportSize( { width, height: 900 } );
        const metrics: Record< Variant, Surface[] > = {
          legacy: [],
          blocks: [],
        };
        const navigation: Record< Variant, NavigationLink[] > = {
          legacy: [],
          blocks: [],
        };
        const footerLinks: Record< Variant, FooterLink[] > = {
          legacy: [],
          blocks: [],
        };
        for ( const variant of variants ) {
          await page.goto( '/' );
          await page.setContent( documents[ variant ], {
            waitUntil: 'networkidle',
          } );
          await page.addStyleTag( {
            content: '* { transition: none !important; }',
          } );
          await page.evaluate( () => document.fonts.ready );
          metrics[ variant ] = await page.evaluate(
            ( selectors ) =>
              selectors.map( ( selector ) => {
                const element = document.querySelector( selector )!;
                const rect = element.getBoundingClientRect();
                const style = getComputedStyle( element );
                return {
                  x: rect.x,
                  y: rect.y,
                  width: rect.width,
                  height: rect.height,
                  color: style.color,
                  background: style.backgroundColor,
                  fontSize: style.fontSize,
                };
              } ),
            pairs.map( ( pair ) => pair[ variant === 'legacy' ? 0 : 1 ] )
          );
          if ( width <= 1024 ) {
            await page
              .locator(
                variant === 'legacy'
                  ? '.celina-nav-toggle'
                  : '.celina-block-menu > summary'
              )
              .click();
          }
          const legacyLinks =
            width <= 1024
              ? '.celina-mobile-nav a'
              : '.celina-primary-menu > li > a';
          const blockLinks =
            width <= 1024
              ? '.celina-block-primary-panel a'
              : 'nav.celina-block-primary .wp-block-navigation__container > li > a';
          const linkSelector = variant === 'legacy' ? legacyLinks : blockLinks;
          navigation[ variant ] = await page
            .locator( linkSelector )
            .evaluateAll( ( links ) =>
              links.map( ( link ) => {
                const rect = link.getBoundingClientRect();
                const style = getComputedStyle( link );
                return {
                  text: link.textContent?.trim(),
                  x: rect.x,
                  y: rect.y,
                  width: rect.width,
                  height: rect.height,
                  font: style.fontSize,
                  color: style.color,
                };
              } )
            );
          if ( width <= 1024 ) {
            await page.keyboard.press( 'Escape' );
          }
          footerLinks[ variant ] = await page
            .locator(
              variant === 'legacy'
                ? '.celina-footer a'
                : '.celina-block-footer a'
            )
            .evaluateAll( ( links ) =>
              links.map( ( link ) => {
                const rect = link.getBoundingClientRect();
                const style = getComputedStyle( link );
                return {
                  text: link.textContent?.trim(),
                  href: link.getAttribute( 'href' ) ?? '',
                  x: rect.x,
                  y: rect.y,
                  width: rect.width,
                  height: rect.height,
                  color: style.color,
                  weight: style.fontWeight,
                };
              } )
            );
        }
        expect
          .soft( navigation.blocks.length )
          .toBe( navigation.legacy.length );
        for ( let i = 0; i < navigation.legacy.length; i++ ) {
          const actual = navigation.blocks[ i ]!;
          const legacy = navigation.legacy[ i ]!;
          expect.soft( actual.text ).toBe( legacy.text );
          expect
            .soft( actual.color, `${ width } link ${ i } color` )
            .toBe( legacy.color );
          expect
            .soft( actual.font, `${ width } link ${ i } font` )
            .toBe( legacy.font );
          for ( const property of dimensions ) {
            expect
              .soft(
                Math.abs( actual[ property ] - legacy[ property ] ),
                `${ width } link ${ i } ${ property }: ${ actual[ property ] } vs ${ legacy[ property ] }`
              )
              .toBeLessThanOrEqual( 2 );
          }
        }
        expect
          .soft( footerLinks.blocks.length )
          .toBe( footerLinks.legacy.length );
        for ( let i = 0; i < footerLinks.legacy.length; i++ ) {
          const legacy = footerLinks.legacy[ i ]!;
          const actual = footerLinks.blocks.find(
            ( link ) => link.text === legacy.text && link.href === legacy.href
          )!;
          expect.soft( actual.text ).toBe( legacy.text );
          expect
            .soft( actual.color, `${ width } footer link ${ i } color` )
            .toBe( legacy.color );
          expect
            .soft( actual.weight, `${ width } footer link ${ i } weight` )
            .toBe( legacy.weight );
          for ( const property of [ 'x', 'y' ] as const ) {
            expect
              .soft(
                Math.abs( actual[ property ] - legacy[ property ] ),
                `${ width } footer link ${ i } ${ property }`
              )
              .toBeLessThanOrEqual( 2 );
          }
        }
        for ( const [ index, pair ] of pairs.entries() ) {
          const actual = metrics.blocks[ index ]!;
          const legacy = metrics.legacy[ index ]!;
          for ( const property of dimensions ) {
            expect
              .soft(
                Math.abs( actual[ property ] - legacy[ property ] ),
                `${ width } ${ pair[ 1 ] } ${ property }: ${ actual[ property ] } vs ${ legacy[ property ] }`
              )
              .toBeLessThanOrEqual( 2 );
          }
          expect
            .soft( actual.color, `${ width } ${ pair[ 1 ] } color` )
            .toBe( legacy.color );
          expect
            .soft( actual.background, `${ width } ${ pair[ 1 ] } background` )
            .toBe( legacy.background );
          expect
            .soft( actual.fontSize, `${ width } ${ pair[ 1 ] } font` )
            .toBe( legacy.fontSize );
        }
        if ( width <= 1024 ) {
          const toggle = page.locator( '.celina-block-menu > summary' );
          await toggle.click();
          const panel = page.locator( '.celina-block-primary-panel' );
          await expect( panel ).toBeVisible();
          const header = await page
            .locator( '.celina-block-header' )
            .boundingBox();
          const menu = await panel.boundingBox();
          expect(
            Math.abs( menu!.y - ( header!.y + header!.height ) )
          ).toBeLessThanOrEqual( 1 );
          await expect(
            panel.locator( '.celina-block-mobile-cta' )
          ).toBeVisible();
          await page.keyboard.press( 'Escape' );
          await expect( panel ).toBeHidden();
          await expect( toggle ).toBeFocused();
        }
        await page.evaluate( () => window.scrollTo( 0, 200 ) );
        expect(
          ( await page.locator( '.celina-block-header' ).boundingBox() )!.y
        ).toBe( 0 );
        expect(
          ( await page.locator( '.celina-block-utility' ).boundingBox() )!.y
        ).toBeLessThan( 0 );
      }
    } );
  }
}

for ( const sales of [ true, false ] ) {
  test( `desktop dropdown preserves legacy geometry and emphasis (sales ${ sales })`, async ( {
    page,
  } ) => {
    const response = await page.request.get(
      '/tests/integration/block-shell-parity-fixture.php' +
        ( sales ? '' : '?legacy_style=1' )
    );
    const documents = parseDocuments( await response.json() );
    await page.setViewportSize( { width: 1440, height: 900 } );
    const results: Record< Variant, Dropdown | undefined > = {
      legacy: undefined,
      blocks: undefined,
    };
    for ( const variant of variants ) {
      await page.goto( '/' );
      await page.setContent( documents[ variant ], {
        waitUntil: 'networkidle',
      } );
      const parent = page
        .locator(
          variant === 'legacy'
            ? '.celina-primary-menu > li:has(.sub-menu) > a'
            : 'nav.celina-block-primary .wp-block-navigation-submenu > a'
        )
        .first();
      await parent.hover();
      const menu = page
        .locator(
          variant === 'legacy'
            ? '.celina-primary-menu .sub-menu'
            : 'nav.celina-block-primary .wp-block-navigation__submenu-container'
        )
        .first();
      await expect( menu ).toBeVisible();
      await menu.locator( 'a' ).first().focus();
      results[ variant ] = await menu.evaluate( ( element ) => {
        const measure = ( item: Element ) => {
          const rect = item.getBoundingClientRect();
          const style = getComputedStyle( item );
          return {
            x: rect.x,
            y: rect.y,
            width: rect.width,
            height: rect.height,
            color: style.color,
            background: style.backgroundColor,
            shadow: style.boxShadow,
            border: style.borderTopWidth,
          };
        };
        return {
          menu: measure( element ),
          links: Array.from( element.querySelectorAll( 'a' ) ).map( measure ),
        };
      } );
    }
    const blocks = results.blocks!;
    const legacyResult = results.legacy!;
    const comparisons: [ DropdownSurface, DropdownSurface ][] = [
      [ blocks.menu, legacyResult.menu ],
      ...blocks.links.map(
        ( item, index ): [ DropdownSurface, DropdownSurface ] => [
          item,
          legacyResult.links[ index ]!,
        ]
      ),
    ];
    for ( const [ actual, legacy ] of comparisons ) {
      for ( const property of dimensions ) {
        expect
          .soft(
            Math.abs( actual[ property ] - legacy[ property ] ),
            `dropdown ${ property }: ${ actual[ property ] } vs ${ legacy[ property ] }`
          )
          .toBeLessThanOrEqual( 2 );
      }
      for ( const property of [
        'color',
        'background',
        'shadow',
        'border',
      ] as const ) {
        expect
          .soft( actual[ property ], `dropdown ${ property }` )
          .toBe( legacy[ property ] );
      }
    }
  } );
}

test( 'custom logo keeps the legacy width on phones and larger screens', async ( {
  page,
} ) => {
  for ( const sales of [ true, false ] ) {
    const documents = parseDocuments(
      await (
        await page.request.get(
          '/tests/integration/block-shell-parity-fixture.php?logo=1' +
            ( sales ? '' : '&legacy_style=1' )
        )
      ).json()
    );
    for ( const width of [ 1440, 768, 375 ] ) {
      await page.setViewportSize( { width, height: 900 } );
      const rects: Record< Variant, Geometry | null > = {
        legacy: null,
        blocks: null,
      };
      for ( const variant of variants ) {
        await page.goto( '/' );
        await page.setContent( documents[ variant ], {
          waitUntil: 'networkidle',
        } );
        rects[ variant ] = await page
          .locator(
            variant === 'legacy'
              ? '.celina-header .custom-logo'
              : '.celina-block-header-logo img'
          )
          .boundingBox();
      }
      for ( const property of dimensions ) {
        expect(
          Math.abs( rects.blocks![ property ] - rects.legacy![ property ] ),
          `${ width } logo ${ property }`
        ).toBeLessThanOrEqual( 1 );
      }
    }
  }
} );

function parseDocuments( value: unknown ): Record< Variant, string > {
  if (
    ! value ||
    typeof value !== 'object' ||
    ! ( 'legacy' in value ) ||
    typeof value.legacy !== 'string' ||
    ! ( 'blocks' in value ) ||
    typeof value.blocks !== 'string'
  ) {
    throw new Error( 'Invalid shell parity fixture.' );
  }
  return { legacy: value.legacy, blocks: value.blocks };
}
