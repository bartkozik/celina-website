// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import {
  ACHIEVEMENT_TEMPLATE,
  ALLOWED_ACHIEVEMENT_BLOCKS,
  normalizeAchievementsVariant,
  type AchievementsTimelineAttributes,
} from './model';
import save from './save';

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );
  const useBlockProps = Object.assign( ( props = {} ) => props, {
    save: ( props = {} ) => props,
  } );
  const InnerBlocks = {
    Content: () =>
      React.createElement( 'span', {
        'data-celina-inner-blocks-content': 'true',
      } ),
  };

  return {
    InnerBlocks,
    RichText: {
      Content: ( {
        tagName,
        value,
        ...props
      }: {
        className?: string;
        tagName: string;
        value: string;
      } ) =>
        React.createElement( tagName, {
          ...props,
          dangerouslySetInnerHTML: { __html: value },
        } ),
    },
    useBlockProps,
  };
} );

describe( 'Achievements Timeline parent', () => {
  it( 'declares a restricted collection and controlled facts/timeline variant', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/achievements-timeline',
      version: '0.1.0',
      title: 'Achievements Timeline — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'awards',
      textdomain: 'celina-blocks',
      allowedBlocks: [ 'celina/achievement' ],
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        introduction: { type: 'string', default: '' },
        variant: {
          type: 'string',
          enum: [ 'facts', 'timeline', 'stats' ],
          default: 'facts',
        },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'seeds exactly three allowed achievement items', () => {
    expect( ALLOWED_ACHIEVEMENT_BLOCKS ).toEqual( [ 'celina/achievement' ] );
    expect( ACHIEVEMENT_TEMPLATE ).toEqual( [
      [ 'celina/achievement', {} ],
      [ 'celina/achievement', {} ],
      [ 'celina/achievement', {} ],
    ] );
  } );

  it( 'falls back to facts for unknown variants', () => {
    expect( normalizeAchievementsVariant( 'timeline' ) ).toBe( 'timeline' );
    expect( normalizeAchievementsVariant( 'stats' ) ).toBe( 'stats' );
    expect( normalizeAchievementsVariant( 'unknown' ) ).toBe( 'facts' );
  } );

  it( 'configures restricted InnerBlocks with a button appender', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/achievements-timeline/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain(
      'allowedBlocks={ ALLOWED_ACHIEVEMENT_BLOCKS }'
    );
    expect( editor ).toContain( 'template={ ACHIEVEMENT_TEMPLATE }' );
    expect( editor ).toContain( 'InnerBlocks.ButtonBlockAppender' );
  } );

  it.each( [
    [ 'achievements-timeline', 'celina-achievements-timeline__items' ],
    [ 'capability-cards', 'celina-capability-cards__grid' ],
    [ 'industry-cards', 'celina-industry-cards__grid' ],
    [ 'facility-cards', 'celina-facility-cards__grid' ],
    [ 'resource-list', 'celina-resource-list__items' ],
  ] as const )(
    'lets the %s editor wrapper span the complete collection grid',
    ( block, containerClass ) => {
      const styles = readFileSync(
        `wordpress/plugins/celina-blocks/src/blocks/${ block }/style.scss`,
        'utf8'
      );

      expect( styles ).toContain(
        `.${ containerClass } > .block-editor-inner-blocks {`
      );
      expect( styles ).toContain( 'grid-column: 1 / -1;' );
      expect( styles ).toContain( 'min-width: 0;' );
    }
  );

  it.each( [
    [ 'facts', 'ul' ],
    [ 'timeline', 'ol' ],
    [ 'stats', 'ul' ],
  ] as const )( 'serializes %s as a semantic %s list', ( variant, listTag ) => {
    const attributes: AchievementsTimelineAttributes = {
      eyebrow: 'Our history',
      heading: 'Built through experience',
      introduction: '<p>Selected milestones.</p>',
      variant,
    };
    const markup = renderToStaticMarkup( save( { attributes } ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const section = fragment.querySelector(
      `section.celina-achievements-timeline--${ variant }`
    );

    expect( fragment.firstElementChild ).toBe( section );
    expect( section?.querySelector( 'h2' )?.textContent ).toBe(
      'Built through experience'
    );
    expect(
      section?.querySelector(
        `${ listTag }.celina-achievements-timeline__items`
      )
    ).not.toBeNull();
    expect(
      section?.querySelector( '[data-celina-inner-blocks-content="true"]' )
    ).not.toBeNull();
  } );
} );
