import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function wp( ...args: string[] ): void {
  execFileSync( 'pnpm', [ 'exec', 'wp-env', 'run', 'cli', 'wp', ...args ], {
    env: process.env,
    stdio: 'pipe',
  } );
}

test( 'legacy review switch preserves the native redesigned pages', async ( {
  page,
} ) => {
  test.skip(
    ! process.env.WP_ENV_HOME,
    'Requires the isolated content E2E runner.'
  );
  // This test never submits an inquiry or loads vendor embeds.
  await page.route( /^https:\/\//, ( route ) => route.abort() );
  wp( 'theme', 'mod', 'remove', 'celina_sales_review' );
  await page.goto( '/' );
  const original = await page.locator( 'main' ).innerHTML();
  try {
    wp(
      'eval-file',
      '/var/www/html/tests/integration/sales-review-contract.php'
    );
    wp( 'theme', 'mod', 'set', 'celina_sales_review', '1' );
    for ( const width of [ 360, 390, 768, 1024, 1025, 1280 ] ) {
      await page.setViewportSize( { width, height: 900 } );
      await page.goto( '/' );
      await expect( page.locator( 'h1' ) ).toHaveText(
        'Tents, Shelters, & Custom Fabric Manufacturing'
      );
      await expect( page.locator( '.celina-header-cta' ) ).toBeVisible();
      if ( width <= 1024 ) {
        await expect( page.locator( '.celina-primary' ) ).toBeHidden();
        await expect( page.locator( '.celina-nav-toggle' ) ).toBeVisible();
      } else {
        await expect( page.locator( '.celina-primary' ) ).toBeVisible();
        await expect( page.locator( '.celina-nav-toggle' ) ).toBeHidden();
      }
      await expect( page.locator( '.celina-sales-cta h2' ) ).toHaveCSS(
        'color',
        'rgb(255, 108, 13)'
      );
      await expect( page.locator( '.celina-hero' ) ).toHaveCSS(
        'display',
        'flex'
      );
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth
      );
      expect( overflow ).toBe( false );
      await page.locator( '.celina-hero__actions a' ).first().click();
      await expect( page ).toHaveURL( /\/contact\/$/ );
      await expect( page.locator( 'h1' ) ).toHaveText(
        'Tell Us About Your Project'
      );
      await expect( page.locator( '.celina-hubspot-form' ) ).toHaveCount( 1 );
      await expect( page.locator( '.celina-hero' ) ).toHaveCount( 0 );
    }
    await page.goto( '/industries/' );
    await expect(
      page.locator( '.celina-hero__actions a' ).first()
    ).toHaveText( 'Discuss Your Project' );
    await page.goto( '/about/' );
    await expect(
      page.locator( '.celina-footer-menu a[aria-current="page"]' )
    ).toHaveText( 'About CELINA' );
    await page.emulateMedia( { reducedMotion: 'reduce' } );
    await expect(
      page.getByRole( 'button', { name: 'Back to top' } )
    ).toBeHidden();
    await page.locator( '.celina-footer' ).scrollIntoViewIfNeeded();
    await page.getByRole( 'button', { name: 'Back to top' } ).click();
    await expect.poll( () => page.evaluate( () => window.scrollY ) ).toBe( 0 );
    await expect(
      page.getByRole( 'button', { name: 'Back to top' } )
    ).toBeHidden();

    const history = page.locator( '.celina-sales-history details' );
    await expect( history ).not.toHaveAttribute( 'open' );
    await history.locator( 'summary' ).focus();
    await page.keyboard.press( 'Enter' );
    await expect( history ).toHaveAttribute( 'open' );
    await expect( page.locator( '.celina-leadership img' ) ).toHaveCount( 7 );
  } finally {
    wp( 'theme', 'mod', 'remove', 'celina_sales_review' );
  }
  await page.goto( '/' );
  await expect( page.locator( '.celina-review-page' ) ).toHaveCount( 0 );
  expect( await page.locator( 'main' ).innerHTML() ).toBe( original );
} );
