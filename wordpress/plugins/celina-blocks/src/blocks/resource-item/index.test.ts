// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import {
  normalizeResourceItemAttributes,
  type ResourceItemAttributes,
} from './model';
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

describe( 'Resource Item', () => {
  it( 'can only be inserted inside Resource List', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/resource-item',
      version: '0.1.0',
      title: 'Resource Item — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'download',
      textdomain: 'celina-blocks',
      parent: [ 'celina/resource-list' ],
      attributes: {
        title: { type: 'string', default: '' },
        description: { type: 'string', default: '' },
        metadata: { type: 'string', default: '' },
        fileId: { type: 'number', default: 0 },
        fileUrl: { type: 'string', default: '' },
        linkLabel: { type: 'string', default: '' },
      },
      supports: { html: false, reusable: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'normalizes malformed values without accepting unsafe file URLs', () => {
    expect(
      normalizeResourceItemAttributes( {
        title: 'Capabilities brochure',
        description: '<p>Overview.</p>',
        metadata: 'PDF · 2 MB',
        fileId: -4,
        fileUrl: 'javascript:alert(1)',
        linkLabel: 'Download PDF',
      } )
    ).toEqual( {
      title: 'Capabilities brochure',
      description: '<p>Overview.</p>',
      metadata: 'PDF · 2 MB',
      fileId: 0,
      fileUrl: '',
      linkLabel: 'Download PDF',
    } satisfies ResourceItemAttributes );
  } );

  it( 'uses Media Library document selection', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/resource-item/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'MediaUpload' );
    expect( editor ).toContain( 'MediaUploadCheck' );
    expect( editor ).toContain( 'DOCUMENT_MIME_TYPES' );
  } );

  it( 'serializes a real file as a download link', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          title: 'Capabilities brochure',
          description: '<p>Overview of CELINA capabilities.</p>',
          metadata: 'PDF · 2 MB',
          fileId: 23,
          fileUrl: '/wp-content/uploads/capabilities.pdf',
          linkLabel: 'Download PDF',
        },
      } )
    );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const link = fragment.querySelector( 'a.celina-resource-item__link' );

    expect(
      fragment.querySelector( 'li.celina-resource-item' )
    ).not.toBeNull();
    expect( link?.getAttribute( 'href' ) ).toBe(
      '/wp-content/uploads/capabilities.pdf'
    );
    expect( link?.hasAttribute( 'download' ) ).toBe( true );
    expect( link?.textContent ).toBe( 'Download PDF' );
    expect( fragment.querySelector( '[role="status"]' ) ).toBeNull();
  } );

  it( 'renders a keyboard-readable unavailable state without a dead link', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          title: 'Annual report',
          description: '<p>Coming soon.</p>',
          metadata: 'PDF',
          fileId: 0,
          fileUrl: '',
          linkLabel: 'Download report',
        },
      } )
    );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;

    expect( fragment.querySelector( 'a' ) ).toBeNull();
    expect( markup ).not.toContain( 'href="#"' );
    expect( fragment.querySelector( '[role="status"]' )?.textContent ).toBe(
      'Resource file not yet available'
    );
  } );
} );
