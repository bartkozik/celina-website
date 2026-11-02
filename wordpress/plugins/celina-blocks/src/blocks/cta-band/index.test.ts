// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import {
  getRenderableCtaLinks,
  isSafeLinkUrl,
  type CtaBandAttributes,
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

const completeAttributes: CtaBandAttributes = {
  eyebrow: 'Ready to begin?',
  heading: 'Build the next mission with CELINA',
  description: 'Talk with our team about your requirements.',
  primaryLabel: 'Contact us',
  primaryUrl: '/contact',
  primaryNewTab: false,
  secondaryLabel: 'View capabilities',
  secondaryUrl: 'https://example.test/capabilities',
  secondaryNewTab: true,
};

describe( 'CTA Band', () => {
  it( 'declares the complete native block contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/cta-band',
      version: '0.1.0',
      title: 'CTA Band — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'megaphone',
      textdomain: 'celina-blocks',
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        description: { type: 'string', default: '' },
        primaryLabel: { type: 'string', default: '' },
        primaryUrl: { type: 'string', default: '' },
        primaryNewTab: { type: 'boolean', default: false },
        secondaryLabel: { type: 'string', default: '' },
        secondaryUrl: { type: 'string', default: '' },
        secondaryNewTab: { type: 'boolean', default: false },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
    } );
  } );

  it.each( [
    'https://example.test/path',
    'http://example.test',
    'mailto:contact@example.invalid',
    'tel:+15551234567',
    '/contact',
    '#contact-form',
  ] )( 'accepts the supported URL %s', ( url ) => {
    expect( isSafeLinkUrl( url ) ).toBe( true );
  } );

  it.each( [
    '',
    '#',
    'javascript:alert(1)',
    '//example.test',
    'data:text/html,x',
  ] )( 'rejects the unsupported URL %s', ( url ) => {
    expect( isSafeLinkUrl( url ) ).toBe( false );
  } );

  it( 'keeps only complete buttons with safe URLs', () => {
    expect( getRenderableCtaLinks( completeAttributes ) ).toEqual( [
      {
        label: 'Contact us',
        url: '/contact',
        newTab: false,
        variant: 'primary',
      },
      {
        label: 'View capabilities',
        url: 'https://example.test/capabilities',
        newTab: true,
        variant: 'secondary',
      },
    ] );

    expect(
      getRenderableCtaLinks( {
        ...completeAttributes,
        primaryLabel: 'Missing URL',
        primaryUrl: '',
        secondaryUrl: 'javascript:alert(1)',
      } )
    ).toEqual( [] );
  } );

  it( 'serializes semantic content and safe link attributes', () => {
    const markup = renderToStaticMarkup(
      save( { attributes: completeAttributes } )
    );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;
    const section = fragment.querySelector( 'section.celina-cta-band' );
    const links = fragment.querySelectorAll( 'a' );

    expect( fragment.firstElementChild ).toBe( section );
    expect( section?.querySelector( 'h2' )?.textContent ).toBe(
      'Build the next mission with CELINA'
    );
    expect(
      section?.querySelector( '.celina-cta-band__eyebrow' )?.textContent
    ).toBe( 'Ready to begin?' );
    expect(
      section?.querySelector( '.celina-cta-band__description' )?.textContent
    ).toBe( 'Talk with our team about your requirements.' );
    expect( links ).toHaveLength( 2 );
    expect( links[ 0 ]?.hasAttribute( 'target' ) ).toBe( false );
    expect( links[ 0 ]?.hasAttribute( 'rel' ) ).toBe( false );
    expect( links[ 1 ]?.getAttribute( 'target' ) ).toBe( '_blank' );
    expect( links[ 1 ]?.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
  } );

  it( 'does not serialize incomplete or unsafe anchors', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          ...completeAttributes,
          primaryLabel: '',
          secondaryUrl: 'javascript:alert(1)',
        },
      } )
    );

    expect( markup ).not.toContain( '<a' );
  } );

  it( 'uses native rich text, URL, and new-tab controls in the editor', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/cta-band/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'RichText' );
    expect( editor ).toContain( 'URLInputButton' );
    expect( editor ).toContain( 'ToggleControl' );
  } );
} );
