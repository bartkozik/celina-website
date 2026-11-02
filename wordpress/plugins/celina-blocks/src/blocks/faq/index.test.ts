// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import Edit from './edit';
import { ALLOWED_FAQ_BLOCKS, FAQ_TEMPLATE } from './model';
import save from './save';

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );
  const useBlockProps = Object.assign( ( props = {} ) => props, {
    save: ( props = {} ) => props,
  } );
  const InnerBlocks = Object.assign(
    ( {
      allowedBlocks,
      renderAppender,
      template,
    }: {
      allowedBlocks?: string[];
      renderAppender?: unknown;
      template?: unknown;
    } ) =>
      React.createElement( 'div', {
        'data-allowed-blocks': JSON.stringify( allowedBlocks ),
        'data-render-appender':
          renderAppender === InnerBlocks.ButtonBlockAppender ? 'button' : '',
        'data-template': JSON.stringify( template ),
      } ),
    {
      ButtonBlockAppender: () => null,
      Content: () =>
        React.createElement( 'span', {
          'data-celina-inner-blocks-content': 'true',
        } ),
    }
  );
  const RichText = Object.assign(
    ( {
      tagName,
      value,
      ...props
    }: {
      className?: string;
      tagName: string;
      value: string;
    } ) => React.createElement( tagName, props, value ),
    {
      Content: ( {
        tagName,
        value,
        ...props
      }: {
        className?: string;
        tagName: string;
        value: string;
      } ) => React.createElement( tagName, props, value ),
    }
  );

  return { InnerBlocks, RichText, useBlockProps };
} );

describe( 'FAQ parent', () => {
  it( 'allows and seeds only FAQ items', () => {
    expect( ALLOWED_FAQ_BLOCKS ).toEqual( [ 'celina/faq-item' ] );
    expect( FAQ_TEMPLATE ).toEqual( [ [ 'celina/faq-item', {} ] ] );
  } );

  it( 'declares the restricted container metadata contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/faq',
      version: '0.1.0',
      title: 'FAQ — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'editor-help',
      textdomain: 'celina-blocks',
      allowedBlocks: [ 'celina/faq-item' ],
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
      },
      supports: { html: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'serializes its inner blocks inside a semantic FAQ section', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          eyebrow: 'Common Questions',
          heading: 'About CELINA FAQ',
        },
      } )
    );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;

    const section = fragment.querySelector( 'section.celina-faq' );
    expect( section ).not.toBeNull();
    expect(
      section?.querySelector( '[data-celina-inner-blocks-content="true"]' )
    ).not.toBeNull();
    expect( fragment.firstElementChild ).toBe( section );
    expect(
      section?.querySelector( '.celina-faq__eyebrow' )?.textContent
    ).toBe( 'Common Questions' );
    expect(
      section?.querySelector( 'h2.celina-faq__heading' )?.textContent
    ).toBe( 'About CELINA FAQ' );
  } );

  it( 'configures its editor canvas with the restricted FAQ item template', () => {
    const markup = renderToStaticMarkup(
      createElement( Edit, {
        attributes: { eyebrow: '', heading: '' },
        setAttributes: vi.fn(),
      } )
    );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const innerBlocks = fragment.querySelector( '[data-allowed-blocks]' );

    expect( innerBlocks?.getAttribute( 'data-allowed-blocks' ) ).toBe(
      JSON.stringify( [ 'celina/faq-item' ] )
    );
    expect( innerBlocks?.getAttribute( 'data-template' ) ).toBe(
      JSON.stringify( [ [ 'celina/faq-item', {} ] ] )
    );
    expect( innerBlocks?.getAttribute( 'data-render-appender' ) ).toBe(
      'button'
    );
  } );

  it( 'uses theme-token fallbacks for its constrained content width and spacing', () => {
    const styles = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/faq/style.scss',
      'utf8'
    );

    expect( styles ).toContain( 'var(--celina-grid-width, 80rem)' );
    expect( styles ).toContain( 'var(--celina-gutter, 1rem)' );
    expect( styles ).toContain( 'var(--celina-section-space, 4rem)' );
  } );
} );
