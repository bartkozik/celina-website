import { expect, test } from '@playwright/test';

type PerformanceMetrics = {
  cls: number;
  lcp: number;
};

type MeasuredWindow = Window & {
  __celinaPerformance?: PerformanceMetrics;
};

const routes = [
  { name: 'Home', path: '/home/' },
  { name: 'News', path: '/news/' },
  { name: 'Careers', path: '/careers/' },
  { name: '30 Years article', path: '/thirty-years-built-by-people/' },
] as const;
const viewports = [
  { height: 812, name: 'mobile', width: 375 },
  { height: 900, name: 'desktop', width: 1440 },
] as const;
const forbiddenRequest =
  /(?:fonts\.googleapis\.com|fonts\.gstatic\.com|rsms\.me|placedog|wpengine|js\.hsforms\.net|(?:^|[/.])bamboohr\.com(?:[:/]|$))/i;
const paycorEmbedRequest =
  'https://recruitingbypaycor.com/career/iframe.action?clientId=portfolio-unconfigured';

test.describe( 'deterministic local performance safeguards', () => {
  test.skip(
    Boolean( process.env.CELINA_CONTENT_GROUP ),
    'Performance safeguards require the complete managed-content set.'
  );

  for ( const route of routes ) {
    for ( const viewport of viewports ) {
      test( `${ route.name } satisfies local ${ viewport.name } safeguards`, async ( {
        page,
      }, testInfo ) => {
        const requests: string[] = [];
        const paycorLoaderRequests: string[] = [];
        page.on( 'request', ( request ) => requests.push( request.url() ) );
        await page.route(
          'https://recruitingbypaycor.com/**',
          async ( requestRoute ) => {
            paycorLoaderRequests.push( requestRoute.request().url() );
            await requestRoute.abort();
          }
        );
        await page.setViewportSize( viewport );
        await page.addInitScript( () => {
          const measuredWindow = window as MeasuredWindow;
          measuredWindow.__celinaPerformance = { cls: 0, lcp: 0 };

          if ( ! ( 'PerformanceObserver' in window ) ) {
            return;
          }
          if (
            PerformanceObserver.supportedEntryTypes.includes( 'layout-shift' )
          ) {
            new PerformanceObserver( ( list ) => {
              for ( const entry of list.getEntries() ) {
                const shift = entry as PerformanceEntry & {
                  hadRecentInput: boolean;
                  value: number;
                };
                if (
                  ! shift.hadRecentInput &&
                  measuredWindow.__celinaPerformance
                ) {
                  measuredWindow.__celinaPerformance.cls += shift.value;
                }
              }
            } ).observe( { buffered: true, type: 'layout-shift' } );
          }
          if (
            PerformanceObserver.supportedEntryTypes.includes(
              'largest-contentful-paint'
            )
          ) {
            new PerformanceObserver( ( list ) => {
              const latest = list.getEntries().at( -1 );
              if ( latest && measuredWindow.__celinaPerformance ) {
                measuredWindow.__celinaPerformance.lcp = latest.startTime;
              }
            } ).observe( {
              buffered: true,
              type: 'largest-contentful-paint',
            } );
          }
        } );

        const response = await page.goto( route.path, {
          waitUntil: 'networkidle',
        } );
        expect( response?.ok() ).toBe( true );
        await page.evaluate( async () => {
          await document.fonts.ready;
          await new Promise( ( resolve ) => window.setTimeout( resolve, 250 ) );
        } );

        const metrics = await page.evaluate(
          () =>
            ( window as MeasuredWindow ).__celinaPerformance ?? {
              cls: Number.POSITIVE_INFINITY,
              lcp: 0,
            }
        );
        testInfo.annotations.push( {
          description: `${ Math.round( metrics.lcp ) }ms`,
          type: 'observed LCP',
        } );
        expect( metrics.cls ).toBeLessThanOrEqual( 0.1 );
        expect( metrics.lcp ).toBeGreaterThan( 0 );
        expect(
          requests.filter( ( url ) => forbiddenRequest.test( url ) )
        ).toEqual( [] );
        if ( route.name === 'Careers' ) {
          expect( paycorLoaderRequests ).toEqual( [ paycorEmbedRequest ] );
        } else {
          expect( paycorLoaderRequests ).toEqual( [] );
        }

        const duplicateScripts = await page
          .locator( 'script[src]' )
          .evaluateAll( ( scripts ) => {
            const sources = scripts
              .map( ( script ) => script.getAttribute( 'src' ) )
              .filter( ( source ): source is string => Boolean( source ) );
            return sources.filter(
              ( source, index ) => sources.indexOf( source ) !== index
            );
          } );
        expect( duplicateScripts ).toEqual( [] );

        const images = await page
          .locator( '.celina-entry-content img' )
          .evaluateAll( ( nodes ) =>
            nodes.map( ( image ) => ( {
              inHero: Boolean( image.closest( '.wp-block-celina-hero' ) ),
              loading: image.getAttribute( 'loading' ),
            } ) )
          );
        expect(
          images.filter( ( image ) => image.inHero && image.loading === 'lazy' )
        ).toEqual( [] );
        const contentImages = images.filter( ( image ) => ! image.inHero );
        const firstDeferredIndex = contentImages.findIndex(
          ( image ) => image.loading === 'lazy'
        );
        if ( contentImages.length > 3 ) {
          expect(
            firstDeferredIndex,
            'Image-heavy content must defer at least one image.'
          ).toBeGreaterThanOrEqual( 0 );
        }
        const deferredStart =
          firstDeferredIndex === -1 ? contentImages.length : firstDeferredIndex;
        for ( const image of contentImages.slice( deferredStart ) ) {
          expect( image.loading ).toBe( 'lazy' );
        }
      } );
    }
  }
} );
