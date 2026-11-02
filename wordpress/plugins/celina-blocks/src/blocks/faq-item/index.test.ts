// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import { hasFaqContent } from './model';
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

describe( 'FAQ item', () => {
  it( 'requires a nonblank question and a visible answer', () => {
    expect(
      hasFaqContent( { question: 'Question?', answer: '<p>Answer.</p>' } )
    ).toBe( true );
    expect( hasFaqContent( { question: '', answer: '<p>Answer.</p>' } ) ).toBe(
      false
    );
    expect(
      hasFaqContent( {
        question: '<strong> </strong>',
        answer: '<p>Answer.</p>',
      } )
    ).toBe( false );
    expect(
      hasFaqContent( { question: '&nbsp;', answer: '<p>Answer.</p>' } )
    ).toBe( false );
    expect(
      hasFaqContent( { question: 'Question?', answer: '<p>  </p>' } )
    ).toBe( false );
    expect(
      hasFaqContent( { question: 'Question?', answer: '<p>&nbsp;</p>' } )
    ).toBe( false );
    expect(
      hasFaqContent( { question: 'Question?', answer: '<p>\u00a0</p>' } )
    ).toBe( false );
    expect(
      hasFaqContent( { question: 'Question?', answer: '<p>&#xA0;</p>' } )
    ).toBe( false );
  } );

  it( 'treats malformed attributes defensively as incomplete content', () => {
    expect( hasFaqContent( null ) ).toBe( false );
    expect( hasFaqContent( { question: 42, answer: '<p>Answer.</p>' } ) ).toBe(
      false
    );
    expect( hasFaqContent( { question: 'Question?', answer: [] } ) ).toBe(
      false
    );
  } );

  it( 'rejects HTML and character references that resolve to invisible content', () => {
    const invisibleValues = [
      '<span title=">"> </span>',
      '<p><!-- > --></p>',
      '&nbsp',
      '&#160',
      '&#x200b;',
      '&ZeroWidthSpace;',
      '\u200b',
      '\u2060',
      '\ufeff',
    ];

    for ( const value of invisibleValues ) {
      expect( hasFaqContent( { question: 'Question?', answer: value } ) ).toBe(
        false
      );
      expect( hasFaqContent( { question: value, answer: 'Answer.' } ) ).toBe(
        false
      );
    }

    expect( hasFaqContent( { question: 'Question?', answer: '1 < 2' } ) ).toBe(
      true
    );
  } );

  it( 'matches browser parsing for entities and non-rendered elements', () => {
    const invisibleValues = [
      '&ApplyFunction;',
      '&it;',
      '&ic;',
      '<template>hidden</template>',
      '<style>hidden</style>',
      '<script>hidden</script>',
    ];
    const visibleValues = [
      '&ZeroWidthSpace',
      '&ThinSpace',
      '&NewLine',
      '&#128;',
      '&#0;',
    ];

    for ( const value of invisibleValues ) {
      expect( hasFaqContent( { question: 'Question?', answer: value } ) ).toBe(
        false
      );
      expect( hasFaqContent( { question: value, answer: 'Answer.' } ) ).toBe(
        false
      );
    }

    for ( const value of visibleValues ) {
      expect( hasFaqContent( { question: 'Question?', answer: value } ) ).toBe(
        true
      );
      expect( hasFaqContent( { question: value, answer: 'Answer.' } ) ).toBe(
        true
      );
    }

    expect(
      renderToStaticMarkup(
        save( {
          attributes: {
            question: 'Question?',
            answer: '<template>hidden</template>',
          },
        } )
      )
    ).toBe( '' );
  } );

  it( 'rejects default-ignorable characters and structurally hidden content', () => {
    const invisibleValues = [
      '\uFE0F',
      '\u034F',
      '&#xfe0f;',
      '&#x034f;',
      '<span hidden>hidden</span>',
      '<dialog>hidden</dialog>',
    ];

    for ( const value of invisibleValues ) {
      expect( hasFaqContent( { question: 'Question?', answer: value } ) ).toBe(
        false
      );
      expect( hasFaqContent( { question: value, answer: 'Answer.' } ) ).toBe(
        false
      );
    }

    expect(
      hasFaqContent( {
        question: '<dialog open>Question?</dialog>',
        answer: '<dialog open>Answer.</dialog>',
      } )
    ).toBe( true );
    expect(
      hasFaqContent( { question: '<span>Question?</span>', answer: 'Answer.' } )
    ).toBe( true );
    expect(
      renderToStaticMarkup(
        save( {
          attributes: {
            question: 'Question?',
            answer: '<span hidden>hidden</span>',
          },
        } )
      )
    ).toBe( '' );
  } );

  it( 'declares the parent-only FAQ item metadata contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/faq-item',
      version: '0.1.0',
      title: 'FAQ Item — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'editor-help',
      textdomain: 'celina-blocks',
      parent: [ 'celina/faq' ],
      attributes: {
        question: { type: 'string', default: '' },
        answer: { type: 'string', default: '' },
      },
      supports: { html: false, reusable: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it( 'serializes native disclosure markup with the expected item classes', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: { question: 'Question?', answer: '<p>Answer.</p>' },
      } )
    );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;

    const details = fragment.querySelector( 'details.celina-faq-item' );
    expect( details ).not.toBeNull();
    expect( details?.children ).toHaveLength( 2 );
    expect(
      details?.children[ 0 ]?.matches( 'summary.celina-faq-item__question' )
    ).toBe( true );
    expect(
      details?.children[ 1 ]?.matches( 'div.celina-faq-item__answer' )
    ).toBe( true );
    expect( details?.children[ 0 ]?.textContent ).toBe( 'Question?' );
    expect( details?.children[ 1 ]?.textContent ).toBe( 'Answer.' );
  } );

  it( 'omits incomplete content from saved markup', () => {
    expect(
      renderToStaticMarkup(
        save( { attributes: { question: '', answer: '<p>Answer.</p>' } } )
      )
    ).toBe( '' );
    expect(
      renderToStaticMarkup(
        save( {
          attributes: { question: 'Question?', answer: '<p>&nbsp;</p>' },
        } )
      )
    ).toBe( '' );
    expect(
      renderToStaticMarkup(
        save( {
          attributes: {
            question: 'Question?',
            answer: '<span title=">"> </span>',
          },
        } )
      )
    ).toBe( '' );
  } );

  it( 'uses accessible disclosure styling with token fallbacks', () => {
    const styles = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/faq-item/style.scss',
      'utf8'
    );

    expect( styles ).toContain( 'var(--celina-color-border, #e5e5e5)' );
    expect( styles ).toContain( 'var(--celina-space-4, 1rem)' );
    expect( styles ).toContain( 'var(--celina-color-muted, #666)' );
    expect( styles ).toContain(
      '3px solid var(--celina-color-citrus, #ff6c0d)'
    );
    expect( styles ).toContain( 'outline-offset: 3px' );
    expect( styles ).toContain( '@media (hover: hover)' );
    expect( styles ).toContain( '.celina-faq-item summary:hover' );
    expect( styles ).toContain( 'padding-block: 0' );
    expect( styles ).toContain( 'content: "-";' );
    expect( styles ).not.toContain( 'content: "−";' );
    expect( styles ).not.toContain( String.raw`content: "\2212";` );
    expect( styles ).not.toContain(
      'margin-block: calc(-1 * var(--celina-space-2, 0.5rem))'
    );

    const hoverRule = styles.match(
      /\.celina-faq-item summary:hover\s*\{(?<rule>[^}]*)\}/s
    );
    expect( hoverRule?.groups?.rule ).toContain(
      'color: var(--celina-color-citrus, #ff6c0d)'
    );
    expect( hoverRule?.groups?.rule ).not.toContain( 'background-color' );
  } );
} );
