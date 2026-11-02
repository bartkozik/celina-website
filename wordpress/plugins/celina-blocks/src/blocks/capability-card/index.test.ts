// @vitest-environment jsdom

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import type { CapabilityCardAttributes } from './model';
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

describe( 'Capability Card item', () => {
  it( 'can only be inserted inside its parent collection', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/capability-card',
      version: '0.1.0',
      title: 'Capability Card — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'index-card',
      textdomain: 'celina-blocks',
      parent: [ 'celina/capability-cards' ],
      attributes: {
        heading: { type: 'string', default: '' },
        content: { type: 'string', default: '' },
        proof: { type: 'string', default: '' },
      },
      supports: { html: false, reusable: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'serializes an article with fixed h3 hierarchy and optional proof', () => {
    const attributes: CapabilityCardAttributes = {
      heading: 'Rapid deployment',
      content: '<p>Configured for demanding environments.</p>',
      proof: 'Delivered worldwide',
    };
    const markup = renderToStaticMarkup( save( { attributes } ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const article = fragment.querySelector( 'article.celina-capability-card' );

    expect( fragment.firstElementChild ).toBe( article );
    expect( article?.querySelector( 'h3' )?.textContent ).toBe(
      'Rapid deployment'
    );
    expect(
      article?.querySelector( '.celina-capability-card__content' )?.textContent
    ).toBe( 'Configured for demanding environments.' );
    expect(
      article?.querySelector( '.celina-capability-card__proof' )?.textContent
    ).toBe( 'Delivered worldwide' );
  } );

  it( 'omits the proof element when no proof is provided', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          heading: 'Engineering',
          content: '<p>Integrated systems.</p>',
          proof: '',
        },
      } )
    );

    expect( markup ).not.toContain( 'celina-capability-card__proof' );
  } );
} );
