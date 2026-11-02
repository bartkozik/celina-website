// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import {
  ALLOWED_FACILITY_BLOCKS,
  FACILITY_TEMPLATE,
  normalizeFacilityColumns,
  type FacilityCollectionAttributes,
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

describe( 'Facility Cards parent', () => {
  it( 'declares a restricted collection with controlled columns', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/facility-cards',
      version: '0.1.0',
      title: 'Facility Cards — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'building',
      textdomain: 'celina-blocks',
      allowedBlocks: [ 'celina/facility-card' ],
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        introduction: { type: 'string', default: '' },
        columns: { type: 'number', enum: [ 2, 3 ], default: 2 },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'seeds exactly two allowed facility cards', () => {
    expect( ALLOWED_FACILITY_BLOCKS ).toEqual( [ 'celina/facility-card' ] );
    expect( FACILITY_TEMPLATE ).toEqual( [
      [ 'celina/facility-card', {} ],
      [ 'celina/facility-card', {} ],
    ] );
  } );

  it( 'allowlists two and three column layouts', () => {
    expect( normalizeFacilityColumns( 3 ) ).toBe( 3 );
    expect( normalizeFacilityColumns( 4 ) ).toBe( 2 );
  } );

  it( 'configures restricted InnerBlocks with a button appender', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/facility-cards/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'allowedBlocks={ ALLOWED_FACILITY_BLOCKS }' );
    expect( editor ).toContain( 'template={ FACILITY_TEMPLATE }' );
    expect( editor ).toContain( 'InnerBlocks.ButtonBlockAppender' );
  } );

  it( 'serializes its heading and children inside a semantic section', () => {
    const attributes: FacilityCollectionAttributes = {
      eyebrow: 'Locations',
      heading: 'Our facilities',
      introduction: '<p>Where our teams work.</p>',
      columns: 3,
    };
    const markup = renderToStaticMarkup( save( { attributes } ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const section = fragment.querySelector( 'section.celina-facility-cards' );

    expect( fragment.firstElementChild ).toBe( section );
    expect( section?.classList ).toContain(
      'celina-facility-cards--columns-3'
    );
    expect( section?.querySelector( 'h2' )?.textContent ).toBe(
      'Our facilities'
    );
    expect(
      section?.querySelector( '[data-celina-inner-blocks-content="true"]' )
    ).not.toBeNull();
  } );
} );
