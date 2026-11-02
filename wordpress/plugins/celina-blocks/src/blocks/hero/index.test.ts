// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import { getEditableButtons, normalizeButtons } from './model';
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
      } ) => React.createElement( tagName, props, value ),
    },
    useBlockProps,
  };
} );

describe( 'Hero', () => {
  it( 'keeps at most two complete buttons', () => {
    expect(
      normalizeButtons( [
        { label: 'Learn more', url: '/learn-more' },
        { label: '', url: '/incomplete' },
        { label: 'Apply now', url: '/apply' },
        { label: 'Contact us', url: '/contact' },
      ] )
    ).toEqual( [
      { label: 'Learn more', url: '/learn-more' },
      { label: 'Apply now', url: '/apply' },
    ] );
  } );

  it( 'rejects unsupported variants', () => {
    expect( () => normalizeButtons( [], 'landing' ) ).toThrow(
      'invalid Hero variant'
    );
  } );

  it( 'prepares at most two structurally valid button drafts for editing', () => {
    expect(
      getEditableButtons( [
        null,
        { label: 'Missing URL' },
        { label: '', url: '' },
        { label: 'Still drafting', url: '' },
        { label: 'Ignored third draft', url: '/third' },
      ] )
    ).toEqual( [
      { label: '', url: '' },
      { label: 'Still drafting', url: '' },
    ] );
  } );

  it( 'imports editor controls directly from WordPress components', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/hero/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( "from '@wordpress/components';" );
    expect( editor ).not.toContain( 'wp.components' );
  } );

  it( 'uses the shared section eyebrow in a readable citrus', () => {
    const styles = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/hero/style.scss',
      'utf8'
    );
    const shared = readFileSync(
      'wordpress/plugins/celina-blocks/src/shared/_eyebrow.scss',
      'utf8'
    );

    expect( styles ).toContain( '@include eyebrow.section-eyebrow();' );
    expect( shared ).toContain( 'text-transform: uppercase;' );
    expect( shared ).toContain( 'letter-spacing: 0.18em;' );
    expect( shared ).toContain( 'background: currentcolor;' );
  } );

  it( 'serializes semantic content and decorative backgrounds without an image', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          eyebrow: 'Join the team',
          heading: 'Make a difference',
          description: 'Build a better future with CELINA.',
          buttons: [
            { label: 'View careers', url: '/careers' },
            { label: 'Meet the team', url: '/team' },
            { label: 'Ignored', url: '/ignored' },
          ],
          backgroundUrl: 'https://example.test/hero.jpg',
          variant: 'careers',
        },
      } )
    );

    const documentFragment = document.createElement( 'div' );
    documentFragment.innerHTML = markup;
    const section = documentFragment.querySelector( 'section' );
    const actions = documentFragment.querySelectorAll( 'a' );

    expect( section ).not.toBeNull();
    expect( section?.classList ).toContain( 'celina-hero' );
    expect( section?.classList ).toContain( 'celina-hero--careers' );
    expect( section?.style.backgroundImage ).toContain( 'hero.jpg' );
    expect(
      documentFragment.querySelector( 'p.celina-hero__eyebrow' )?.textContent
    ).toBe( 'Join the team' );
    expect(
      documentFragment.querySelector( 'h1.celina-hero__heading' )?.textContent
    ).toBe( 'Make a difference' );
    expect(
      documentFragment.querySelector( 'p.celina-hero__description' )
        ?.textContent
    ).toBe( 'Build a better future with CELINA.' );
    expect( actions ).toHaveLength( 2 );
    expect( Array.from( actions, ( action ) => action.textContent ) ).toEqual( [
      'View careers',
      'Meet the team',
    ] );
    expect(
      Array.from( actions, ( action ) => action.getAttribute( 'href' ) )
    ).toEqual( [ '/careers', '/team' ] );
    expect( documentFragment.querySelector( 'img' ) ).toBeNull();
  } );
} );
