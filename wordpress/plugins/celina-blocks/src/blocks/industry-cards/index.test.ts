// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import {
  ALLOWED_INDUSTRY_BLOCKS,
  INDUSTRY_TEMPLATE,
  normalizeIndustryColumns,
  type IndustryCollectionAttributes,
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

describe( 'Industry Cards parent', () => {
  it( 'declares a restricted collection with controlled columns', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/industry-cards',
      version: '0.1.0',
      title: 'Industry Cards — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'grid-view',
      textdomain: 'celina-blocks',
      allowedBlocks: [ 'celina/industry-card' ],
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        introduction: { type: 'string', default: '' },
        columns: { type: 'number', enum: [ 1, 2, 3, 4 ], default: 3 },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'seeds exactly three allowed industry cards', () => {
    expect( ALLOWED_INDUSTRY_BLOCKS ).toEqual( [ 'celina/industry-card' ] );
    expect( INDUSTRY_TEMPLATE ).toEqual( [
      [ 'celina/industry-card', {} ],
      [ 'celina/industry-card', {} ],
      [ 'celina/industry-card', {} ],
    ] );
  } );

  it( 'allowlists 1/2/3/4-column layouts', () => {
    expect( normalizeIndustryColumns( 1 ) ).toBe( 1 );
    expect( normalizeIndustryColumns( 2 ) ).toBe( 2 );
    expect( normalizeIndustryColumns( 4 ) ).toBe( 4 );
    expect( normalizeIndustryColumns( 5 ) ).toBe( 3 );
    expect( normalizeIndustryColumns( 'wide' ) ).toBe( 3 );
  } );

  it( 'configures restricted InnerBlocks and a button appender', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/industry-cards/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'allowedBlocks={ ALLOWED_INDUSTRY_BLOCKS }' );
    expect( editor ).toContain( 'template={ INDUSTRY_TEMPLATE }' );
    expect( editor ).toContain( 'InnerBlocks.ButtonBlockAppender' );
  } );

  it( 'serializes its heading and children inside a semantic section', () => {
    const attributes: IndustryCollectionAttributes = {
      eyebrow: 'Markets',
      heading: 'Where we operate',
      introduction: '<p>Solutions for demanding sectors.</p>',
      columns: 2,
    };
    const markup = renderToStaticMarkup( save( { attributes } ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const section = fragment.querySelector( 'section.celina-industry-cards' );

    expect( fragment.firstElementChild ).toBe( section );
    expect( section?.classList ).toContain(
      'celina-industry-cards--columns-2'
    );
    expect( section?.querySelector( 'h2' )?.textContent ).toBe(
      'Where we operate'
    );
    expect(
      section?.querySelector( '[data-celina-inner-blocks-content="true"]' )
    ).not.toBeNull();
  } );
} );
