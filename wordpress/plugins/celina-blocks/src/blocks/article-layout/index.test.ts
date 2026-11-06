// @vitest-environment jsdom

import { existsSync, readFileSync } from 'node:fs';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import Edit from './edit';
import { ALLOWED_ARTICLE_BLOCKS, ARTICLE_TEMPLATE } from './model';
import save from './save';

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );
  const addBlockClass = ( props: Record< string, unknown > = {} ) => ( {
    ...props,
    className: [ 'wp-block-celina-article-layout', props.className ]
      .filter( Boolean )
      .join( ' ' ),
  } );
  const useBlockProps = Object.assign( addBlockClass, {
    save: addBlockClass,
  } );
  const InnerBlocks = Object.assign(
    ( {
      allowedBlocks,
      renderAppender,
      template,
      templateLock,
    }: {
      allowedBlocks?: readonly string[];
      renderAppender?: unknown;
      template?: unknown;
      templateLock?: boolean;
    } ) =>
      React.createElement( 'div', {
        'data-allowed-blocks': JSON.stringify( allowedBlocks ),
        'data-render-appender':
          renderAppender === InnerBlocks.ButtonBlockAppender ? 'button' : '',
        'data-template': JSON.stringify( template ),
        'data-template-lock': String( templateLock ),
      } ),
    {
      ButtonBlockAppender: () => null,
      Content: () =>
        React.createElement( 'span', {
          'data-celina-inner-blocks-content': 'true',
        } ),
    }
  );

  return { InnerBlocks, useBlockProps };
} );

const expectedAllowedBlocks = [
  'core/paragraph',
  'core/heading',
  'core/image',
  'core/gallery',
  'core/list',
  'core/pullquote',
  'core/quote',
  'core/table',
  'core/buttons',
  'core/separator',
  'core/embed',
  'core/group',
  'core/columns',
  'core/media-text',
  'celina/media-contact',
];

describe( 'CELINA Article Layout', () => {
  it( 'declares the searchable static block contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/article-layout',
      version: '0.1.0',
      title: 'CELINA Article Layout — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'media-document',
      description: 'A flexible editorial layout for CELINA news articles.',
      keywords: [ 'article', 'news', 'story' ],
      textdomain: 'celina-blocks',
      attributes: {},
      allowedBlocks: expectedAllowedBlocks,
      supports: { html: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'allows the exact editorial block set', () => {
    expect( ALLOWED_ARTICLE_BLOCKS ).toEqual( expectedAllowedBlocks );
  } );

  it( 'seeds the seven editable article sections in editorial order', () => {
    expect( ARTICLE_TEMPLATE ).toHaveLength( 7 );
    expect( ARTICLE_TEMPLATE.map( ( [ name ] ) => name ) ).toEqual( [
      'core/paragraph',
      'core/heading',
      'core/paragraph',
      'core/image',
      'core/pullquote',
      'core/paragraph',
      'celina/media-contact',
    ] );
    expect( ARTICLE_TEMPLATE[ 0 ]?.[ 1 ] ).toMatchObject( {
      className: 'celina-article-layout__lead',
      placeholder: expect.any( String ) as unknown,
    } );
    expect( ARTICLE_TEMPLATE[ 1 ]?.[ 1 ] ).toMatchObject( {
      level: 2,
      placeholder: expect.any( String ) as unknown,
    } );
    expect( ARTICLE_TEMPLATE[ 3 ]?.[ 1 ] ).toMatchObject( {
      caption: expect.any( String ) as unknown,
      sizeSlug: 'large',
    } );
    expect( ARTICLE_TEMPLATE[ 4 ]?.[ 1 ] ).toMatchObject( {
      citation: 'Quote source',
      value: 'Pull quote.',
    } );
  } );

  it( 'keeps the starter editable and exposes the allowed appender', () => {
    const markup = renderToStaticMarkup( createElement( Edit ) );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const innerBlocks = fragment.querySelector( '[data-allowed-blocks]' );

    expect( innerBlocks?.getAttribute( 'data-allowed-blocks' ) ).toBe(
      JSON.stringify( expectedAllowedBlocks )
    );
    expect( innerBlocks?.getAttribute( 'data-template' ) ).toBe(
      JSON.stringify( ARTICLE_TEMPLATE )
    );
    expect( innerBlocks?.getAttribute( 'data-template-lock' ) ).toBe( 'false' );
    expect( innerBlocks?.getAttribute( 'data-render-appender' ) ).toBe(
      'button'
    );
  } );

  it( 'serializes only the exact static layout wrapper and inner content', () => {
    const markup = renderToStaticMarkup( save() );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const wrapper = fragment.querySelector(
      'div.wp-block-celina-article-layout.celina-article-layout'
    );

    expect( fragment.firstElementChild ).toBe( wrapper );
    expect( wrapper?.attributes ).toHaveLength( 1 );
    expect(
      wrapper?.querySelector( '[data-celina-inner-blocks-content="true"]' )
    ).not.toBeNull();
  } );

  it( 'stays static and does not globally restrict the post editor', () => {
    const plugin = readFileSync(
      'wordpress/plugins/celina-blocks/celina-blocks.php',
      'utf8'
    );
    const registration = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/article-layout/index.tsx',
      'utf8'
    );

    expect(
      existsSync(
        'wordpress/plugins/celina-blocks/src/blocks/article-layout/render.php'
      )
    ).toBe( false );
    expect( registration ).toContain(
      'registerBlockType( metadata, { edit: Edit, save } )'
    );
    expect( plugin ).not.toContain( 'allowed_block_types_all' );
  } );
} );
