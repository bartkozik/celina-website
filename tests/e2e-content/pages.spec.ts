import AxeBuilder from '@axe-core/playwright';
import { severeAxeViolations } from '../support/accessibility';
import { expect, test } from '@playwright/test';

type ContentGroup =
  | 'brand-pages'
  | 'offer-pages'
  | 'people-contact-pages'
  | 'news-resources'
  | 'legal-pages';

type ManagedPage = {
  group: ContentGroup;
  h1: string;
  images?: boolean;
  markers: string[];
  path: string;
};

const pages: ManagedPage[] = [
  {
    group: 'brand-pages',
    h1: 'Tents, Shelters, & Custom Fabric Manufacturing',
    markers: [
      'Capabilities That Strengthen Every Partnership',
      'Supporting Customers Across Diverse Industries',
    ],
    path: '/home/',
  },
  {
    group: 'brand-pages',
    h1: 'Built in Celina, Ohio. Trusted around the world.',
    markers: [ 'Three Decades of Manufacturing', 'About CELINA FAQ' ],
    path: '/about/',
  },
  {
    group: 'brand-pages',
    h1: 'Built on Trust. Proven Through Experience.',
    markers: [ 'What We Hold Ourselves To', 'Why CELINA FAQ' ],
    path: '/why-celina/',
  },
  {
    group: 'offer-pages',
    h1: 'The Capabilities Behind Every CELINA Solution.',
    markers: [ 'What CELINA Does Best', 'Tell Us About Your Project' ],
    path: '/capabilities/',
  },
  {
    group: 'offer-pages',
    h1: 'One Company. Four Specialized Industries.',
    markers: [
      'Government & Military',
      'Specialized Expertise. Shared Standards.',
    ],
    path: '/industries/',
  },
  {
    group: 'offer-pages',
    h1: 'Investing in the Communities That Support Us.',
    markers: [
      'A Lasting Commitment to Community.',
      'Building Strong Communities Together.',
    ],
    path: '/community/',
  },
  {
    group: 'people-contact-pages',
    h1: 'Build More Than Products. Build Your Future.',
    markers: [
      'Why Choose CELINA Careers',
      'Ready to Build Your Future with CELINA?',
    ],
    path: '/careers/',
  },
  {
    group: 'people-contact-pages',
    h1: 'Tell Us About Your Project',
    markers: [ 'Contact CELINA FAQ', 'Our Locations' ],
    path: '/contact/',
  },
  {
    group: 'news-resources',
    h1: 'News, Announcements & Company Updates',
    images: false,
    markers: [ 'Latest CELINA Corporate News', 'Media Inquiries' ],
    path: '/news/',
  },
  {
    group: 'news-resources',
    h1: 'Corporate Resources',
    images: false,
    markers: [ 'Featured Corporate Resources', 'Additional Resources' ],
    path: '/resources/',
  },
  {
    group: 'news-resources',
    h1: 'Thirty Years Built by People',
    markers: [ 'Where It All Began', 'Thank You for Being Part of Our Story' ],
    path: '/thirty-years-built-by-people/',
  },
  {
    group: 'news-resources',
    h1: 'CELINA Turns 30 – Growing from Celina Tent to CELINA Manufacturing',
    markers: [
      'Where CELINA Manufacturing All Began',
      'Thank You for Being Part of Our Story',
    ],
    path: '/celina-manufacturing-anniversary/',
  },
  {
    group: 'news-resources',
    h1: 'ATA Tent Training: Why Education Has Become Part of CELINA’s Mission',
    markers: [
      'Raising the Standard for the Tent Rental Industry',
      'Knowledge Creates Better Outcomes',
    ],
    path: '/ata-tent-training-celina/',
  },
  {
    group: 'news-resources',
    h1: 'One CELINA Brand: Building the Next Chapter of Our Company',
    markers: [
      'Moving Forward as One CELINA Brand',
      'Preparing for the Future',
    ],
    path: '/celina-brand-consilidation/',
  },
  {
    group: 'news-resources',
    h1: 'CELINA Builds Strong Foundation with Recycled Rubble',
    markers: [
      'A Foundation With Local History',
      'Manufacturing and Community Go Hand in Hand',
    ],
    path: '/celina-elementary-school-rubble-recycled/',
  },
  {
    group: 'legal-pages',
    h1: 'Privacy Policy',
    images: false,
    markers: [ '1. Information We Collect', '16. Contact Information' ],
    path: '/privacy-policy/',
  },
  {
    group: 'legal-pages',
    h1: 'Terms & Conditions',
    images: false,
    markers: [ '1. Website Use', '18. Contact Information' ],
    path: '/terms-of-use/',
  },
];

const selectedGroup = process.env.CELINA_CONTENT_GROUP as
  | ContentGroup
  | undefined;
const selectedPages = selectedGroup
  ? pages.filter( ( page ) => page.group === selectedGroup )
  : pages;
const viewports = [
  { height: 812, name: 'mobile', width: 375 },
  { height: 900, name: 'desktop', width: 1440 },
] as const;

for ( const managedPage of selectedPages ) {
  for ( const viewport of viewports ) {
    test( `${ managedPage.path } renders approved content on ${ viewport.name }`, async ( {
      page,
    } ) => {
      await page.setViewportSize( viewport );
      const response = await page.goto( managedPage.path, {
        waitUntil: 'domcontentloaded',
      } );

      expect( response?.ok() ).toBe( true );
      const main = page.locator( 'main' );
      await expect( main.locator( 'h1:visible' ) ).toHaveCount( 1 );
      await expect( main.locator( 'h1:visible' ) ).toHaveText( managedPage.h1 );
      for ( const marker of managedPage.markers ) {
        await expect(
          main.getByRole( 'heading', { name: marker, exact: true } )
        ).toBeVisible();
      }

      if ( managedPage.images !== false ) {
        const imageSources = await main
          .locator( 'img' )
          .evaluateAll( ( images ) =>
            images.map( ( image ) => image.getAttribute( 'src' ) )
          );
        expect( imageSources.length ).toBeGreaterThan( 0 );
        for ( const source of imageSources ) {
          expect( source?.trim() ).toBeTruthy();
          const imageUrl = new URL( source ?? '', page.url() );
          const pageUrl = new URL( page.url() );
          expect( imageUrl.hostname ).toBe( 'localhost' );
          expect( imageUrl.port ).toBe( pageUrl.port );
        }
      }

      const links = await main
        .locator( 'a' )
        .evaluateAll( ( anchors ) =>
          anchors.map( ( anchor ) => anchor.getAttribute( 'href' ) )
        );
      for ( const href of links ) {
        expect( href?.trim() ).toBeTruthy();
        expect( href?.trim() ).not.toBe( '#' );
        expect( () => new URL( href ?? '', page.url() ) ).not.toThrow();
      }

      const hasHorizontalOverflow = await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth
      );
      expect( hasHorizontalOverflow ).toBe( false );

      const axe = await new AxeBuilder( { page } ).analyze();
      expect( severeAxeViolations( axe ) ).toEqual( [] );
    } );
  }
}

test( 'legacy markets route redirects to the client-approved industries route', async ( {
  page,
} ) => {
  test.skip(
    selectedGroup !== undefined && selectedGroup !== 'offer-pages',
    'Industries is introduced by the offer-pages group.'
  );

  const response = await page.goto( '/markets/', {
    waitUntil: 'domcontentloaded',
  } );

  expect( response?.ok() ).toBe( true );
  await expect( page ).toHaveURL( /\/industries\/$/ );
} );

test( 'brand import does not publish an incomplete Reading settings tuple', async ( {
  page,
} ) => {
  test.skip(
    selectedGroup !== 'brand-pages',
    'Reading settings are introduced by the brand page group.'
  );

  const response = await page.goto( '/', { waitUntil: 'domcontentloaded' } );

  expect( response?.ok() ).toBe( true );
  await expect(
    page.getByRole( 'heading', {
      level: 1,
      name: 'Tents, Shelters, & Custom Fabric Manufacturing',
    } )
  ).toHaveCount( 0 );
} );

test( 'managed news content uses the editorial index and single-article presentation', async ( {
  page,
} ) => {
  test.skip(
    selectedGroup !== undefined && selectedGroup !== 'news-resources',
    'Corporate News is introduced by the news and resources group.'
  );

  const newsResponse = await page.goto( '/news/', {
    waitUntil: 'domcontentloaded',
  } );
  expect( newsResponse?.ok() ).toBe( true );
  await expect( page.locator( '.celina-news-grid--news-index' ) ).toHaveCount(
    1
  );
  await expect(
    page.locator( '.celina-news-grid__featured .celina-news-featured-card' )
  ).toHaveCount( 1 );
  await expect(
    page.locator( '.celina-news-featured-card .celina-news-card__category' )
  ).toHaveText( 'Community & Education' );
  await expect( page.locator( '.celina-news-featured-card h3' ) ).toHaveText(
    'CELINA Builds Strong Foundation with Recycled Rubble'
  );
  await expect(
    page.locator( '.celina-news-featured-card .celina-news-card__media img' )
  ).toHaveAttribute( 'src', /article-placeholder\.svg$/ );

  const articleResponse = await page.goto( '/thirty-years-built-by-people/', {
    waitUntil: 'domcontentloaded',
  } );
  expect( articleResponse?.ok() ).toBe( true );
  const article = page.locator( '.celina-single-article' );
  await expect( article.locator( 'h1' ) ).toHaveText(
    'Thirty Years Built by People'
  );
  await expect( article.locator( 'h1' ) ).toHaveCount( 1 );
  await expect( article.locator( '.celina-article-category' ) ).toHaveText(
    'Company Milestones'
  );
  await expect( article.locator( '.celina-article-meta time' ) ).toBeVisible();
  await expect(
    article.locator( '.celina-article-reading-time' )
  ).toContainText( 'min read' );
  await expect(
    article.locator( '.celina-article-media img' )
  ).toHaveAttribute( 'src', /article-placeholder\.svg$/ );
  const axe = await new AxeBuilder( { page } ).analyze();
  expect( severeAxeViolations( axe ) ).toEqual( [] );
} );
