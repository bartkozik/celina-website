// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import {
  ALLOWED_RESOURCE_BLOCKS,
  RESOURCE_TEMPLATE,
  normalizeResourceVariant,
  type ResourceListAttributes,
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

describe( 'Resource List parent', () => {
  it( 'declares a restricted collection and controlled presentation', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/resource-list',
      version: '0.1.0',
      title: 'Resource List — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'media-document',
      textdomain: 'celina-blocks',
      allowedBlocks: [ 'celina/resource-item' ],
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        introduction: { type: 'string', default: '' },
        variant: {
          type: 'string',
          enum: [ 'standard', 'featured' ],
          default: 'standard',
        },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'seeds exactly three allowed resource items', () => {
    expect( ALLOWED_RESOURCE_BLOCKS ).toEqual( [ 'celina/resource-item' ] );
    expect( RESOURCE_TEMPLATE ).toEqual( [
      [ 'celina/resource-item', {} ],
      [ 'celina/resource-item', {} ],
      [ 'celina/resource-item', {} ],
    ] );
  } );

  it( 'falls back to the standard presentation', () => {
    expect( normalizeResourceVariant( 'featured' ) ).toBe( 'featured' );
    expect( normalizeResourceVariant( 'unknown' ) ).toBe( 'standard' );
  } );

  it( 'configures restricted InnerBlocks with a button appender', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/resource-list/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'allowedBlocks={ ALLOWED_RESOURCE_BLOCKS }' );
    expect( editor ).toContain( 'template={ RESOURCE_TEMPLATE }' );
    expect( editor ).toContain( 'InnerBlocks.ButtonBlockAppender' );
  } );

  it.each( [ 'standard', 'featured' ] as const )(
    'serializes %s with the same semantic list',
    ( variant ) => {
      const attributes: ResourceListAttributes = {
        eyebrow: 'Downloads',
        heading: 'Corporate resources',
        introduction: '<p>Reference materials.</p>',
        variant,
      };
      const markup = renderToStaticMarkup( save( { attributes } ) );
      const fragment = document.createElement( 'div' );
      fragment.innerHTML = markup;
      const section = fragment.querySelector(
        `section.celina-resource-list--${ variant }`
      );

      expect( fragment.firstElementChild ).toBe( section );
      expect(
        section?.querySelector( 'ul.celina-resource-list__items' )
      ).not.toBeNull();
      expect(
        section?.querySelector( '[data-celina-inner-blocks-content="true"]' )
      ).not.toBeNull();
    }
  );
} );
