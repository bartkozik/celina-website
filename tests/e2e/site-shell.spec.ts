import AxeBuilder from '@axe-core/playwright';
import { severeAxeViolations } from '../support/accessibility';
import { expect, test } from '@playwright/test';

const fixturePath = '/';
const parentShellSelector = [
  '.site-header',
  '#site-navigation',
  '.inside-site-info',
  '.footer-widgets-container',
].join( ', ' );

const viewports = [
  { name: 'mobile', width: 375, height: 812 },
  { name: 'tablet', width: 900, height: 900 },
  { name: 'desktop', width: 1440, height: 900 },
] as const;

for ( const viewport of viewports ) {
  test( `${ viewport.name } site shell is usable and accessible`, async ( {
    page,
  } ) => {
    await page.setViewportSize( viewport );
    const response = await page.goto( fixturePath, {
      waitUntil: 'domcontentloaded',
    } );

    expect( response?.ok() ).toBe( true );
    await expect( page ).toHaveURL( new RegExp( `${ fixturePath }$` ) );
    await expect( page.locator( '.celina-header' ) ).toHaveCount( 1 );
    await expect( page.locator( '.celina-header' ) ).toBeVisible();
    await expect( page.locator( '.celina-footer' ) ).toHaveCount( 1 );
    await expect( page.locator( '.celina-footer' ) ).toBeVisible();
    await expect( page.locator( parentShellSelector ) ).toHaveCount( 0 );
    await expect( page.getByRole( 'heading', { level: 1 } ) ).toBeVisible();

    const hasHorizontalOverflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth
    );
    expect( hasHorizontalOverflow ).toBe( false );

    const results = await new AxeBuilder( { page } ).analyze();
    const severeViolations = severeAxeViolations( results );

    expect( severeViolations ).toEqual( [] );
  } );
}

test( 'mobile navigation closes on Escape and resets at the desktop breakpoint', async ( {
  page,
} ) => {
  await page.setViewportSize( { width: 375, height: 812 } );
  await page.goto( fixturePath, { waitUntil: 'domcontentloaded' } );

  const root = page.locator( 'html' );
  const toggle = page.locator( '.celina-nav-toggle' );
  const menu = page.locator( '#celina-mobile-nav' );

  await expect( root ).toHaveClass( /celina-navigation-ready/ );
  await expect( toggle ).toHaveCount( 1 );
  await expect( toggle ).toBeVisible();
  await expect( toggle ).toHaveAttribute( 'aria-label', 'Open navigation' );
  await expect( toggle.locator( '.celina-nav-toggle__line' ) ).toHaveCount( 3 );

  const toggleBox = await toggle.boundingBox();
  expect( toggleBox?.width ).toBe( toggleBox?.height );
  await expect( toggle ).toHaveAttribute(
    'aria-controls',
    'celina-mobile-nav'
  );
  await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
  await expect( menu ).toBeHidden();

  await toggle.click();
  await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
  await expect( toggle ).toHaveAttribute( 'aria-label', 'Close navigation' );
  await expect( menu ).toBeVisible();

  await page.keyboard.press( 'Escape' );
  await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
  await expect( menu ).toBeHidden();
  await expect( toggle ).toBeFocused();

  await toggle.click();
  await expect( menu ).toBeVisible();
  await page.setViewportSize( { width: 1440, height: 900 } );
  await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
  await expect( menu ).toBeHidden();
} );

test( 'tablet uses the dropdown while desktop labels stay on one line', async ( {
  page,
} ) => {
  await page.setViewportSize( { width: 900, height: 900 } );
  await page.goto( fixturePath, { waitUntil: 'domcontentloaded' } );

  await expect( page.locator( '.celina-nav-toggle' ) ).toBeVisible();
  await expect( page.locator( '.celina-primary' ) ).toBeHidden();

  await page.setViewportSize( { width: 1100, height: 900 } );
  await expect( page.locator( '.celina-nav-toggle' ) ).toBeHidden();
  await expect( page.locator( '.celina-primary' ) ).toBeVisible();

  const labelsStayOnOneLine = await page
    .locator( '.celina-primary-menu > li > a' )
    .evaluateAll( ( links ) =>
      links.every(
        ( link ) => 'nowrap' === getComputedStyle( link ).whiteSpace
      )
    );
  expect( labelsStayOnOneLine ).toBe( true );
} );
