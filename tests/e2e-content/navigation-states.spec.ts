import { execFileSync } from 'node:child_process';
import { expect, test, type Locator } from '@playwright/test';

// Border color alone is not feedback when the design has no visible border.
async function visibleTreatment( link: Locator ) {
  return link.evaluate( ( element ) => {
    const style = getComputedStyle( element );
    const overlay = getComputedStyle( element, '::before' );
    return {
      background: style.backgroundColor,
      color: style.color,
      opacity: style.opacity,
      overlay: overlay.boxShadow,
      shadow: style.boxShadow,
      underline: style.textDecorationLine,
    };
  } );
}

test( 'article navigation provides hover and keyboard feedback without shifting content', async ( {
  page,
} ) => {
  test.skip(
    Boolean( process.env.CELINA_CONTENT_GROUP ) || ! process.env.WP_ENV_HOME,
    'Requires the isolated content E2E runner.'
  );
  const wp = ( ...args: string[] ) =>
    execFileSync( 'pnpm', [ 'exec', 'wp-env', 'run', 'cli', 'wp', ...args ], {
      encoding: 'utf8',
      env: process.env,
    } );
  // Managed articles are imported in one second; add a dated neighbour to
  // exercise the real PHP navigation without changing their publication dates.
  const output = wp(
    'eval',
    `$categories = get_terms(["taxonomy" => "category", "hide_empty" => true, "fields" => "ids"]);
    $content = '<!-- wp:celina/facility-card {"name":"Test location","phone":"+12025550123","label":"Contact","url":"/contact/"} /-->';
    $content .= '<!-- wp:celina/news-grid ' . wp_json_encode(["layout" => "news-index", "count" => 1, "pagination" => true, "navigationCategories" => $categories]) . ' /-->';
    $content .= '<!-- wp:celina/news-grid {"layout":"grid","count":1,"pagination":true} /-->';
    $id = wp_insert_post(["post_type" => "post", "post_status" => "publish", "post_title" => "Navigation hover fixture", "post_date" => "2020-01-01 12:00:00", "post_content" => $content]); echo "HOVER_POST=$id";`
  );
  const fixtureId = output.match( /HOVER_POST=(\d+)/ )?.[ 1 ];
  expect( fixtureId ).toBeTruthy();
  try {
    await page.goto( '/thirty-years-built-by-people/' );
    const link = page.locator( '.celina-article-navigation a' ).first();
    await link.scrollIntoViewIfNeeded();
    await page.mouse.move( 0, 0 );
    const normal = await visibleTreatment( link );
    const bounds = await link.boundingBox();
    await link.hover();
    await expect.poll( () => visibleTreatment( link ) ).not.toEqual( normal );
    expect( await link.boundingBox() ).toEqual( bounds );

    await page.mouse.move( 0, 0 );
    await page.keyboard.press( 'Tab' );
    await link.focus();
    await expect( link ).toHaveCSS( 'outline-style', 'solid' );
    await expect.poll( () => visibleTreatment( link ) ).not.toEqual( normal );
    const destination = await link.getAttribute( 'href' );
    await link.press( 'Enter' );
    expect( destination ).toBeTruthy();
    await expect( page ).toHaveURL( destination as string );
    // These editable block variants are not enabled in Iza's default pages.
    for ( const selector of [
      '.celina-facility-card__link',
      '.celina-facility-card__contact a',
      '.celina-news-grid__navigation a:not([aria-current])',
      '.celina-news-grid__pagination a',
    ] ) {
      const control = page.locator( selector ).first();
      await control.scrollIntoViewIfNeeded();
      await page.mouse.move( 0, 0 );
      const resting = await visibleTreatment( control );
      await control.hover();
      await expect
        .poll( () => visibleTreatment( control ), { message: selector } )
        .not.toEqual( resting );
    }
  } finally {
    if ( fixtureId ) {
      wp( 'post', 'delete', fixtureId, '--force' );
    }
  }
} );

test( 'shared cards, navigation and buttons retain visible feedback with reduced motion', async ( {
  page,
} ) => {
  test.skip(
    Boolean( process.env.CELINA_CONTENT_GROUP ),
    'Requires all redesigned pages.'
  );
  await page.emulateMedia( { reducedMotion: 'reduce' } );
  for ( const [ route, selectors ] of [
    [
      '/home/',
      [
        '.celina-utility a',
        '.celina-logo, .custom-logo-link',
        '.celina-header-cta',
        '.celina-hero .celina-button',
        '.celina-industry-card__link',
        '.wp-block-button__link',
        '.celina-capability-cards__introduction a',
        '.celina-news-card h3 a',
        '.celina-footer-menu a',
      ],
    ],
    [ '/contact/', [ '.celina-contact-detail a' ] ],
    [ '/news/', [ '.celina-news-card__category', '.celina-media-contact a' ] ],
    [ '/thirty-years-built-by-people/', [ '.celina-article-category a' ] ],
  ] as const ) {
    await page.goto( route );
    for ( const selector of selectors ) {
      const link = page.locator( selector ).first();
      await link.scrollIntoViewIfNeeded();
      await page.mouse.move( 0, 0 );
      const normal = await visibleTreatment( link );
      await link.hover();
      await expect
        .poll( () => visibleTreatment( link ), {
          message: `${ route }: ${ selector }`,
        } )
        .not.toEqual( normal );
    }
  }
} );
