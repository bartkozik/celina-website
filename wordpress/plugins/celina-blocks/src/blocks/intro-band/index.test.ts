// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import { normalizeVariant, type IntroBandAttributes } from './model';
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

describe( 'Intro / Description Band', () => {
  it( 'declares the complete native block contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/intro-band',
      version: '0.1.0',
      title: 'Intro / Description Band — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'align-wide',
      textdomain: 'celina-blocks',
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        content: { type: 'string', default: '' },
        variant: {
          type: 'string',
          enum: [ 'neutral', 'warm' ],
          default: 'neutral',
        },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'allowlists variants and falls back to neutral', () => {
    expect( normalizeVariant( 'neutral' ) ).toBe( 'neutral' );
    expect( normalizeVariant( 'warm' ) ).toBe( 'warm' );
    expect( normalizeVariant( 'unknown' ) ).toBe( 'neutral' );
    expect( normalizeVariant( null ) ).toBe( 'neutral' );
  } );

  it( 'serializes rich text in a semantic section with one controlled variant', () => {
    const attributes: IntroBandAttributes = {
      eyebrow: 'Who we are',
      heading: 'Protection for demanding missions',
      content: '<p>CELINA creates <strong>purpose-built</strong> shelters.</p>',
      variant: 'warm',
    };
    const markup = renderToStaticMarkup( save( { attributes } ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const section = fragment.querySelector( 'section.celina-intro-band' );

    expect( fragment.firstElementChild ).toBe( section );
    expect( section?.classList ).toContain( 'celina-intro-band--warm' );
    expect( section?.classList ).not.toContain( 'celina-intro-band--neutral' );
    expect( section?.querySelector( 'h2' )?.textContent ).toBe(
      'Protection for demanding missions'
    );
    expect(
      section?.querySelector( '.celina-intro-band__content strong' )
        ?.textContent
    ).toBe( 'purpose-built' );
  } );

  it( 'falls back to the neutral class for malformed saved attributes', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          eyebrow: '',
          heading: '',
          content: '<p>Introduction.</p>',
          variant: 'malformed' as IntroBandAttributes[ 'variant' ],
        },
      } )
    );

    expect( markup ).toContain( 'celina-intro-band--neutral' );
    expect( markup ).not.toContain( 'celina-intro-band--malformed' );
  } );

  it( 'uses native rich text and controlled variant inputs in the editor', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/intro-band/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'RichText' );
    expect( editor ).toContain( 'SelectControl' );
    expect( editor ).toContain( "value: 'neutral'" );
    expect( editor ).toContain( "value: 'warm'" );
  } );
} );
