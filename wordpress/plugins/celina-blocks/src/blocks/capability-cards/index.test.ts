// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import {
  ALLOWED_CAPABILITY_BLOCKS,
  CAPABILITY_TEMPLATE,
  normalizeCapabilityColumns,
  normalizeCapabilityVariant,
  type CapabilityCollectionAttributes,
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

describe( 'Capability Cards parent', () => {
  it( 'declares a restricted collection with controlled layout attributes', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/capability-cards',
      version: '0.1.0',
      title: 'Capability Cards — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'screenoptions',
      textdomain: 'celina-blocks',
      allowedBlocks: [ 'celina/capability-card' ],
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        introduction: { type: 'string', default: '' },
        variant: {
          type: 'string',
          enum: [ 'light', 'dark' ],
          default: 'light',
        },
        columns: { type: 'number', enum: [ 2, 3, 4 ], default: 3 },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'seeds exactly three allowed child cards', () => {
    expect( ALLOWED_CAPABILITY_BLOCKS ).toEqual( [ 'celina/capability-card' ] );
    expect( CAPABILITY_TEMPLATE ).toEqual( [
      [ 'celina/capability-card', {} ],
      [ 'celina/capability-card', {} ],
      [ 'celina/capability-card', {} ],
    ] );
  } );

  it( 'allowlists variants and 2/3/4-column layouts', () => {
    expect( normalizeCapabilityVariant( 'dark' ) ).toBe( 'dark' );
    expect( normalizeCapabilityVariant( 'unknown' ) ).toBe( 'light' );
    expect( normalizeCapabilityColumns( 2 ) ).toBe( 2 );
    expect( normalizeCapabilityColumns( 4 ) ).toBe( 4 );
    expect( normalizeCapabilityColumns( 5 ) ).toBe( 3 );
  } );

  it( 'configures restricted InnerBlocks and a button appender', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/capability-cards/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'allowedBlocks={ ALLOWED_CAPABILITY_BLOCKS }' );
    expect( editor ).toContain( 'template={ CAPABILITY_TEMPLATE }' );
    expect( editor ).toContain( 'InnerBlocks.ButtonBlockAppender' );
  } );

  it( 'serializes its heading and children inside a semantic section', () => {
    const attributes: CapabilityCollectionAttributes = {
      eyebrow: 'What we deliver',
      heading: 'Core capabilities',
      introduction: '<p>Mission-ready systems.</p>',
      variant: 'dark',
      columns: 4,
    };
    const markup = renderToStaticMarkup( save( { attributes } ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const section = fragment.querySelector( 'section.celina-capability-cards' );

    expect( fragment.firstElementChild ).toBe( section );
    expect( section?.classList ).toContain( 'celina-capability-cards--dark' );
    expect( section?.classList ).toContain(
      'celina-capability-cards--columns-4'
    );
    expect( section?.querySelector( 'h2' )?.textContent ).toBe(
      'Core capabilities'
    );
    expect(
      section?.querySelector( '[data-celina-inner-blocks-content="true"]' )
    ).not.toBeNull();
  } );
} );
