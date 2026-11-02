// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import { getPhoneHref, normalizeEmail } from './model';
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

describe( 'Media Contact', () => {
  it( 'declares the complete static block contract', () => {
    expect( metadata.attributes ).toEqual( {
      eyebrow: { type: 'string', default: '' },
      heading: { type: 'string', default: 'Media Contact' },
      name: { type: 'string', default: '' },
      role: { type: 'string', default: '' },
      email: { type: 'string', default: '' },
      phone: { type: 'string', default: '' },
    } );
    expect( metadata.supports ).toEqual( { html: false, anchor: true } );
  } );

  it( 'normalizes only safe email and telephone destinations', () => {
    expect( normalizeEmail( ' contact@example.invalid ' ) ).toBe(
      'contact@example.invalid'
    );
    expect( normalizeEmail( 'javascript:alert(1)' ) ).toBe( '' );
    expect( getPhoneHref( ' +1 (419) 555-0123 ' ) ).toBe( 'tel:+14195550123' );
    expect( getPhoneHref( 'call<script>' ) ).toBe( '' );
  } );

  it( 'serializes semantic contact details and safe links', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          eyebrow: 'Press enquiries',
          heading: 'Media Contact',
          name: 'Taylor Example',
          role: 'Communications',
          email: 'contact@example.invalid',
          phone: '+1 (419) 555-0123',
        },
      } )
    );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;

    expect(
      fragment.querySelector( 'aside.celina-media-contact' )
    ).not.toBeNull();
    expect( fragment.querySelector( 'address' )?.textContent ).toContain(
      'Taylor Example'
    );
    expect(
      fragment.querySelector( 'a[href="mailto:contact@example.invalid"]' )
    ).not.toBeNull();
    expect(
      fragment.querySelector( 'a[href="tel:+14195550123"]' )
    ).not.toBeNull();
  } );

  it( 'omits every empty or unsafe optional field', () => {
    const markup = renderToStaticMarkup(
      save( {
        attributes: {
          eyebrow: '',
          heading: 'Media Contact',
          name: '',
          role: '',
          email: 'unsafe',
          phone: 'unsafe',
        },
      } )
    );

    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;

    expect( fragment.querySelector( 'a' ) ).toBeNull();
    expect( markup ).not.toContain( 'celina-media-contact__eyebrow' );
    expect( markup ).not.toContain( 'celina-media-contact__name' );
    expect( markup ).not.toContain( 'celina-media-contact__role' );
  } );

  it( 'uses native rich text and typed contact controls in the editor', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/media-contact/edit.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'RichText' );
    expect( editor ).toContain( 'TextControl' );
    expect( editor ).toContain( 'label="Email"' );
    expect( editor ).toContain( 'label="Phone"' );
  } );
} );
