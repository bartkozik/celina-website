import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const readStyle = ( block: string ): string =>
  readFileSync(
    `wordpress/plugins/celina-blocks/src/blocks/${ block }/style.scss`,
    'utf8'
  );

const readThemeStyle = ( file: string ): string =>
  readFileSync(
    `wordpress/themes/celina-child/assets/css/${ file }.css`,
    'utf8'
  );

const contrastRatio = ( foreground: string, background: string ): number => {
  const luminance = ( hex: string ): number => {
    const channels =
      hex
        .replace( '#', '' )
        .match( /.{2}/g )
        ?.map( ( channel ) => Number.parseInt( channel, 16 ) / 255 ) ?? [];
    const linear = channels.map( ( channel ) =>
      channel <= 0.04045
        ? channel / 12.92
        : ( ( channel + 0.055 ) / 1.055 ) ** 2.4
    );

    return (
      0.2126 * ( linear[ 0 ] ?? 0 ) +
      0.7152 * ( linear[ 1 ] ?? 0 ) +
      0.0722 * ( linear[ 2 ] ?? 0 )
    );
  };
  const values = [ luminance( foreground ), luminance( background ) ].sort(
    ( left, right ) => right - left
  );

  return ( ( values[ 0 ] ?? 0 ) + 0.05 ) / ( ( values[ 1 ] ?? 0 ) + 0.05 );
};

describe( 'CEL-7 design review', () => {
  it( 'uses the approved accessible accent and visible dark-section link states', () => {
    const tokens = readFileSync(
      'wordpress/themes/celina-child/assets/css/tokens.css',
      'utf8'
    );
    const blockRoot = 'wordpress/plugins/celina-blocks/src/blocks';
    const blockStyles = readdirSync( blockRoot )
      .map( ( block ) => `${ blockRoot }/${ block }/style.scss` )
      .filter( existsSync )
      .map( ( path ) => readFileSync( path, 'utf8' ) )
      .join( '\n' );
    const capabilityStyles = readStyle( 'capability-cards' );

    expect( tokens ).toContain( '--celina-color-citrus-dark: #7e2d2b;' );
    expect( contrastRatio( '#7e2d2b', '#ffffff' ) ).toBeGreaterThanOrEqual(
      4.5
    );
    expect( contrastRatio( '#ff6c0d', '#242825' ) ).toBeGreaterThanOrEqual(
      4.5
    );
    expect( blockStyles ).not.toContain( '#b44300' );
    expect( capabilityStyles ).toMatch(
      /\.celina-capability-cards--dark \.celina-capability-cards__introduction a:hover,\s*\.celina-capability-cards--dark \.celina-capability-cards__introduction a:focus-visible\s*\{[^}]*color:\s*var\(--celina-color-citrus, #ff6c0d\);/s
    );
  } );

  it( 'uses the approved extra-bold hierarchy for reviewed block headings', () => {
    const shared = readFileSync(
      'wordpress/plugins/celina-blocks/src/shared/_heading.scss',
      'utf8'
    );

    expect( shared ).toMatch( /font-weight:\s*700;/ );

    for ( const block of [
      'industry-cards',
      'text-image-split',
      'cta-band',
      'achievements-timeline',
      'capability-cards',
    ] ) {
      expect( readStyle( block ) ).toContain(
        '@include heading.section-heading;'
      );
    }

    expect( readStyle( 'industry-card' ) ).toMatch(
      /\.celina-industry-card__title\s*\{[^}]*font-weight:\s*900;/s
    );
    expect( readStyle( 'faq' ) ).toMatch(
      /&__heading\s*\{[^}]*font-weight:\s*700;/s
    );
    expect( readStyle( 'hubspot-form' ) ).toMatch(
      />\s*h2\s*\{[^}]*font-weight:\s*700;/s
    );
  } );

  it( 'structures the Contact form and details like the approved reference', () => {
    const contactPattern = readFileSync(
      'wordpress/themes/celina-child/patterns/page-contact.php',
      'utf8'
    );
    const contentStyles = readThemeStyle( 'content-templates' );

    expect( contactPattern ).toContain( 'celina-contact-form-column' );
    expect( contactPattern ).toContain( 'SEND US A MESSAGE' );
    expect( contactPattern ).toContain( 'celina-contact-card--direct' );
    expect( contactPattern ).toContain( 'celina-contact-card--hours' );
    expect( contactPattern ).toContain( 'celina-contact-detail--phone' );
    expect( contactPattern ).toContain( 'celina-contact-detail--email' );
    expect( contactPattern ).toContain( 'celina-contact-detail--address' );
    expect( contactPattern ).toContain( 'celina-contact-hours__row' );
    expect( contactPattern ).toContain( 'href="tel:+12025550100"' );
    expect( contactPattern ).toContain( 'href="mailto:sales@example.invalid"' );
    expect( contentStyles ).toMatch(
      /\.celina-contact-columns\s*\{[^}]*grid-template-columns:\s*minmax\(0, 2fr\) minmax\(18rem, 1fr\);/s
    );
    expect( contentStyles ).toMatch(
      /\.celina-contact-card\s*\{[^}]*background:\s*var\(--celina-color-surface-muted\);/s
    );
    expect( contentStyles ).toMatch(
      /\.celina-contact-form__eyebrow\s*\{[^}]*color:\s*var\(--celina-color-citrus\);[^}]*font-size:\s*0\.6875rem;[^}]*letter-spacing:\s*0\.18em;/s
    );
    expect( contentStyles ).toMatch(
      /\.celina-contact-form-column \.wp-block-celina-hubspot-form > h2\s*\{[^}]*font-size:\s*clamp\(1\.5rem, 2vw, 1\.875rem\);[^}]*font-weight:\s*700;/s
    );
    expect( contentStyles ).toMatch(
      /\.celina-contact-card\s*\{[^}]*padding:\s*1\.75rem;[^}]*background:\s*var\(--celina-color-surface-muted\);/s
    );
    expect( contentStyles ).toMatch(
      /\.celina-contact-card > h3\s*\{[^}]*font-size:\s*0\.75rem;[^}]*font-weight:\s*700;/s
    );
    expect( contentStyles ).toMatch(
      /\.celina-contact-card a\s*\{[^}]*color:\s*var\(--celina-color-text\);[^}]*font-weight:\s*500;/s
    );
    expect( contentStyles ).toMatch(
      /\.celina-contact-form-column \.wp-block-celina-hubspot-form \.celina-hubspot-form__fallback\s*\{[^}]*order:\s*2;/s
    );
  } );
} );

describe( 'Design feedback rework, 2026-09-22', () => {
  const sectionBlocks = [
    'achievements-timeline',
    'capability-cards',
    'cta-band',
    'facility-cards',
    'hero',
    'industry-cards',
    'intro-band',
    'news-grid',
    'resource-list',
    'text-image-split',
  ];

  it( 'defines one page grid and one hero height as theme tokens', () => {
    const tokens = readThemeStyle( 'tokens' );

    expect( tokens ).toContain( '--celina-grid-width: 73.75rem;' );
    expect( tokens ).toContain( '--celina-gutter: clamp(1rem, 4vw, 2rem);' );
    expect( tokens ).toContain( '--celina-grid-gap: 1.5rem;' );
    expect( tokens ).toContain(
      '--celina-container: min(100% - 2 * var(--celina-gutter), var(--celina-grid-width));'
    );
    expect( tokens ).toContain( '--celina-hero-height: 34rem;' );

    const shell = readThemeStyle( 'site-shell' );

    expect( shell ).toMatch(
      /@media \(min-width: 48rem\)\s*\{[^@]*--celina-hero-height:\s*31rem;/s
    );
    expect( shell ).toMatch(
      /@media \(min-width: 100rem\)\s*\{[^@]*--celina-hero-height:\s*36rem;/s
    );
  } );

  it.each( sectionBlocks )(
    'lays out the %s section on the shared grid',
    ( block ) => {
      const styles = readStyle( block );

      expect( styles ).toContain(
        'var(--celina-gutter, clamp(1rem, 4vw, 2rem))'
      );
      expect( styles ).toContain( 'var(--celina-grid-width, 80rem)' );
      expect( styles ).not.toContain( '--celina-content-width' );
    }
  );

  it( 'puts the header, footer and Sales sections on the same container', () => {
    const shell = readThemeStyle( 'site-shell' );
    const sales = readThemeStyle( 'sales-patterns' );

    expect( shell ).toMatch(
      /\.celina-shell-inner\s*\{[^}]*width:\s*var\(--celina-container\);/s
    );
    expect( sales ).toMatch(
      /\.celina-sales-section\s*\{[^}]*width:\s*var\(--celina-container\);/s
    );
    expect( sales ).not.toMatch( /calc\(100% - 2rem\)/ );
  } );

  it( 'gives every hero the same height and the header gray overlay', () => {
    const hero = readStyle( 'hero' );
    const content = readThemeStyle( 'content-templates' );
    const tokens = readThemeStyle( 'tokens' );

    expect( hero ).toMatch( /min-height:\s*var\(--celina-hero-height,/ );
    expect( hero ).not.toContain( 'celina-hero--careers' );
    expect( hero ).toContain( 'var(--celina-hero-overlay,' );
    expect( tokens ).toContain(
      '--celina-hero-overlay: linear-gradient(90deg, rgb(61 69 67'
    );
    expect( content ).not.toContain( 'rgb(36 40 37' );
    expect( content ).toMatch(
      /\.celina-hero--home-photo\s*\{[^}]*portfolio-image-29\.png/s
    );
  } );

  it( 'keeps Key figures saved markup unchanged and marks proof points only in the editor', () => {
    const edit = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/achievement/edit.tsx',
      'utf8'
    );
    const save = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/achievement/save.tsx',
      'utf8'
    );
    const styles = readStyle( 'achievements-timeline' );

    expect( edit ).toContain( 'celina-achievement--no-figure' );
    expect( save ).not.toContain( 'no-figure' );
    expect( styles ).toContain( '.celina-achievements-timeline--stats' );
    expect( styles ).toContain(
      ':has(> .celina-achievement__marker):not(.celina-achievement--no-figure)'
    );
  } );

  it( 'gives every section the same eyebrow as the hero', () => {
    for ( const block of [
      'hero',
      'achievements-timeline',
      'capability-cards',
      'industry-cards',
      'facility-cards',
      'resource-list',
      'text-image-split',
      'intro-band',
      'cta-band',
    ] ) {
      expect( readStyle( block ) ).toContain(
        '@include eyebrow.section-eyebrow('
      );
    }
  } );

  it( 'drops the orange rule under the header', () => {
    const shell = readThemeStyle( 'site-shell' );

    expect( shell ).not.toMatch(
      /\.celina-header\s*\{[^}]*border-bottom:[^}]*citrus/s
    );
  } );

  it.each( [
    [ 'industry-card', '.celina-industry-card__link' ],
    [ 'facility-card', '.celina-facility-card__link' ],
    [ 'resource-item', '.celina-resource-item__link' ],
  ] as const )(
    'makes the whole %s clickable through its own link with an arrow',
    ( block, link ) => {
      const styles = readStyle( block );

      expect( styles ).toContain( '@use "../../shared/card-link";' );
      expect( styles ).toContain(
        `@include card-link.stretched-link( "${ link }" );`
      );
      expect( styles ).toContain( '@include card-link.arrow-link;' );
    }
  );
} );
