// @vitest-environment jsdom

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import type { AchievementAttributes, AchievementIcon } from './model';
import save from './save';

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );
  const useBlockProps = Object.assign( ( props = {} ) => props, {
    save: ( props = {} ) => props,
  } );

  return {
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

describe( 'Achievement item', () => {
  it( 'can only be inserted inside Achievements Timeline', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/achievement',
      version: '0.1.0',
      title: 'Achievement — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'marker',
      textdomain: 'celina-blocks',
      parent: [ 'celina/achievements-timeline' ],
      attributes: {
        prefix: { type: 'string', default: '' },
        year: { type: 'string', default: '' },
        label: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        icon: {
          type: 'string',
          enum: [
            '',
            'globe',
            'flag',
            'manufacturing',
            'quality',
            'experience',
          ],
          default: '',
        },
        content: { type: 'string', default: '' },
      },
      supports: { html: false, reusable: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'keeps label, heading, and content in meaningful DOM source order', () => {
    const attributes: AchievementAttributes = {
      year: '1991',
      label: 'Established',
      heading: 'CELINA begins',
      content: '<p>A new chapter starts.</p>',
    };
    const markup = renderToStaticMarkup( save( { attributes } ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const item = fragment.querySelector( 'li.celina-achievement' );
    const elements = Array.from( item?.children ?? [] );

    expect( fragment.firstElementChild ).toBe( item );
    expect( elements.map( ( element ) => element.className ) ).toEqual( [
      'celina-achievement__marker',
      'celina-achievement__heading',
      'celina-achievement__content',
    ] );
    expect( elements[ 0 ]?.textContent ).toBe( '1991Established' );
  } );

  it( 'renders an optional quiet prefix before the figure', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          prefix: 'Since',
          year: '1996',
          label: '',
          heading: '30 years of manufacturing experience',
          content: '',
        },
      } )
    );

    expect( markup ).toContain(
      '<div class="celina-achievement__marker"><span class="celina-achievement__prefix">Since</span><span class="celina-achievement__year">1996</span></div>'
    );

    const withoutPrefix = renderToStaticMarkup(
      save( {
        attributes: {
          year: '1996',
          label: '',
          heading: '30 years of manufacturing experience',
          content: '',
        },
      } )
    );

    expect( withoutPrefix ).not.toContain( 'celina-achievement__prefix' );
  } );

  it( 'renders an optional icon and keeps iconless markup unchanged', () => {
    const base: AchievementAttributes = {
      year: '',
      label: '',
      heading: 'Worldwide deployment',
      content: '',
    };
    const withoutIcon = renderToStaticMarkup( save( { attributes: base } ) );
    const withIcon = renderToStaticMarkup(
      save( { attributes: { ...base, icon: 'globe' } } )
    );
    const withUnknownIcon = renderToStaticMarkup(
      save( {
        attributes: { ...base, icon: 'rocket' as unknown as AchievementIcon },
      } )
    );

    expect( withoutIcon ).not.toContain( 'celina-achievement__icon' );
    expect( withUnknownIcon ).toBe( withoutIcon );
    expect( withIcon ).toContain(
      '<span aria-hidden="true" class="celina-achievement__icon celina-achievement__icon--globe">'
    );
    expect( withIcon.indexOf( 'celina-achievement__icon' ) ).toBeLessThan(
      withIcon.indexOf( 'celina-achievement__heading' )
    );
  } );

  it( 'permits facts without a year and omits an empty marker', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          year: '',
          label: '',
          heading: '30+ years',
          content: '<p>Serving demanding markets.</p>',
        },
      } )
    );

    expect( markup ).not.toContain( 'celina-achievement__marker' );
    expect( markup ).toContain( '30+ years' );
  } );
} );
