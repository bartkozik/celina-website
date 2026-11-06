// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Hero from '../blocks/hero/edit';
import type { HeroAttributes } from '../blocks/hero/model';
import Resource, { DOCUMENT_MIME_TYPES } from '../blocks/resource-item/edit';
import type { ResourceItemAttributes } from '../blocks/resource-item/model';

vi.mock( './rich-content-editor', () => ( { default: () => null } ) );

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );
  return {
    useBlockProps: ( props = {} ) => props,
    InspectorControls: ( { children }: { children: React.ReactNode } ) =>
      React.createElement( 'aside', null, children ),
    MediaUploadCheck: ( { children }: { children: React.ReactNode } ) =>
      children,
    MediaUpload: ( {
      render: renderControl,
    }: {
      render: ( args: { open: () => void } ) => React.ReactNode;
    } ) => renderControl( { open: vi.fn() } ),
    RichText: ( {
      tagName,
      value,
      placeholder,
    }: {
      tagName: string;
      value: string;
      placeholder: string;
    } ) => React.createElement( tagName, { 'aria-label': placeholder }, value ),
    URLInputButton: () => null,
  };
} );

vi.mock( '@wordpress/components', async () => {
  const React = await import( 'react' );
  return {
    Button: ( {
      children,
      onClick,
      disabled,
    }: {
      children: React.ReactNode;
      onClick?: () => void;
      disabled?: boolean;
    } ) => React.createElement( 'button', { onClick, disabled }, children ),
    PanelBody: ( { children }: { children: React.ReactNode } ) => children,
    SelectControl: () => null,
    TextControl: ( {
      label,
      value,
      onChange,
    }: {
      label: string;
      value: string;
      onChange: ( value: string ) => void;
    } ) =>
      React.createElement(
        'label',
        null,
        label,
        React.createElement( 'input', {
          value,
          onChange: ( event: React.ChangeEvent< HTMLInputElement > ) =>
            onChange( event.target.value ),
        } )
      ),
  };
} );

afterEach( cleanup );

describe( 'media and action editing', () => {
  it( 'removes both background attributes without touching Hero copy or buttons', () => {
    const onChange = vi.fn();
    const attributes: HeroAttributes = {
      heading: 'Manufacturing',
      eyebrow: '',
      description: '',
      variant: 'standard',
      backgroundId: 31,
      backgroundUrl: 'https://example.test/hero.jpg',
      buttons: [ { label: 'Contact us', url: '/contact/' } ],
    };
    const { container } = render(
      <Hero attributes={ attributes } setAttributes={ onChange } />
    );
    expect( screen.getByText( 'Contact us' ) ).toBeTruthy();
    expect( container.querySelector( '.celina-hero__actions a' ) ).toBeNull();
    fireEvent.click(
      screen.getByRole( 'button', { name: 'Remove background image' } )
    );
    expect( onChange ).toHaveBeenCalledWith( {
      backgroundId: 0,
      backgroundUrl: '',
    } );
  } );

  it( 'keeps an existing resource intact while an invalid URL is drafted, then replaces and clears it', () => {
    const onChange = vi.fn();
    function Harness() {
      const [ attributes, setAttributes ] = useState< ResourceItemAttributes >(
        {
          title: 'Logo pack',
          description: '',
          metadata: '',
          linkLabel: 'Download',
          fileId: 31,
          fileUrl: 'https://example.test/old.pdf',
        }
      );
      return (
        <Resource
          attributes={ attributes }
          setAttributes={ ( next ) => {
            onChange( next );
            setAttributes( ( current ) => ( { ...current, ...next } ) );
          } }
        />
      );
    }
    render( <Harness /> );
    const field = screen.getByLabelText( 'Resource file URL' );
    fireEvent.change( field, { target: { value: 'javascript:alert(1)' } } );
    fireEvent.click( screen.getByRole( 'button', { name: 'Apply file URL' } ) );
    expect( onChange ).not.toHaveBeenCalled();
    expect( screen.getByRole( 'alert' ).textContent ).toMatch( /https|valid/i );
    fireEvent.change( field, {
      target: { value: 'https://example.test/logos.zip' },
    } );
    fireEvent.click( screen.getByRole( 'button', { name: 'Apply file URL' } ) );
    expect( onChange ).toHaveBeenLastCalledWith( {
      fileId: 0,
      fileUrl: 'https://example.test/logos.zip',
    } );
    fireEvent.click(
      screen.getByRole( 'button', { name: 'Remove resource file' } )
    );
    expect( onChange ).toHaveBeenLastCalledWith( { fileId: 0, fileUrl: '' } );
    expect( ( field as HTMLInputElement ).value ).toBe( '' );
  } );

  it( 'allows downloadable logo packs and raster images as well as documents', () => {
    expect( DOCUMENT_MIME_TYPES ).toContain( 'application/zip' );
    expect( DOCUMENT_MIME_TYPES ).toContain( 'image/png' );
    expect( DOCUMENT_MIME_TYPES ).toContain( 'image/jpeg' );
    expect( DOCUMENT_MIME_TYPES ).toContain( 'application/pdf' );
    expect( DOCUMENT_MIME_TYPES ).not.toContain( 'image/svg+xml' );
  } );
} );
