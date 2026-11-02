// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { registerBlockType } from '@wordpress/blocks';
import { createElement, type ReactElement } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import blockRegistry from '../../../blocks.json';
import metadata from './block.json';
import Edit from './edit';
import './index';
import { isPaycorClientIdValid } from './model';

const frontendStyles = readFileSync(
  'wordpress/plugins/celina-blocks/src/blocks/paycor-jobs/style.scss',
  'utf8'
);

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );

  return {
    InspectorControls: ( { children }: { children?: React.ReactNode } ) =>
      React.createElement( 'aside', null, children ),
    useBlockProps: ( props = {} ) => props,
  };
} );

vi.mock( '@wordpress/components', async () => {
  const React = await import( 'react' );

  return {
    PanelBody: ( {
      children,
      title,
    }: {
      children?: React.ReactNode;
      title: string;
    } ) => React.createElement( 'section', { 'aria-label': title }, children ),
    TextControl: ( {
      label,
      onChange,
      value,
    }: {
      label: string;
      onChange: ( value: string ) => void;
      value: string;
    } ) =>
      React.createElement(
        'label',
        null,
        label,
        React.createElement( 'input', {
          'aria-label': label,
          onChange: ( event: React.ChangeEvent< HTMLInputElement > ) =>
            onChange( event.target.value ),
          value,
        } )
      ),
  };
} );

vi.mock( '@wordpress/blocks', () => ( {
  registerBlockType: vi.fn(),
} ) );

const defaultAttributes = {
  heading: 'Current openings',
  clientId: '',
  fallbackText: 'Open positions are also available directly on Paycor.',
  jobsLinkText: 'View open positions on Paycor',
};

type PaycorEditProps = {
  attributes: typeof defaultAttributes;
  setAttributes: ( next: Partial< typeof defaultAttributes > ) => void;
};

const PaycorEdit = Edit as unknown as (
  props: PaycorEditProps
) => ReactElement;

const registerBlockTypeMock = registerBlockType as unknown as {
  mock: {
    calls: Array< [ { name: string }, { save: () => unknown } ] >;
  };
};

describe( 'Paycor Jobs', () => {
  afterEach( cleanup );

  it( 'declares the Paycor dynamic block contract', () => {
    expect( metadata.name ).toBe( 'celina/paycor-jobs' );
    expect( metadata.title ).toBe( 'Paycor Jobs — REDESIGNED' );
    expect( metadata.keywords ).toEqual( [ 'paycor', 'jobs', 'careers' ] );
    expect( metadata.attributes.clientId ).toEqual( {
      type: 'string',
      default: '',
    } );
    expect( metadata.attributes ).toEqual( {
      heading: { type: 'string', default: 'Current openings' },
      clientId: { type: 'string', default: '' },
      fallbackText: {
        type: 'string',
        default: 'Open positions are also available directly on Paycor.',
      },
      jobsLinkText: {
        type: 'string',
        default: 'View open positions on Paycor',
      },
    } );
  } );

  it( 'validates exact Paycor client IDs', () => {
    expect( isPaycorClientIdValid( '00000000000000000000000000000000' ) ).toBe(
      true
    );
    expect( isPaycorClientIdValid( '' ) ).toBe( false );
    expect( isPaycorClientIdValid( '8a7883c691c40f800191c43b305a003z' ) ).toBe(
      false
    );
    expect( isPaycorClientIdValid( '8a7883c691c40f800191c43b305a003' ) ).toBe(
      false
    );
    expect( isPaycorClientIdValid( '00000000000000000000000000000000a' ) ).toBe(
      false
    );
    expect( isPaycorClientIdValid( '8A7883C691C40F800191C43B305A003E' ) ).toBe(
      true
    );
    expect( isPaycorClientIdValid( ' 00000000000000000000000000000000' ) ).toBe(
      false
    );
    expect( isPaycorClientIdValid( '00000000000000000000000000000000 ' ) ).toBe(
      false
    );
  } );

  it( 'registers the dynamic block with a null save function', () => {
    const registration = registerBlockTypeMock.mock.calls.find(
      ( [ block ] ) => block.name === metadata.name
    );

    expect( registration ).toBeDefined();
    expect( registration?.[ 0 ] ).toMatchObject( {
      name: 'celina/paycor-jobs',
    } );
    expect( registration?.[ 1 ].save() ).toBeNull();
  } );

  it( 'includes Paycor Jobs in the alphabetically sorted block registry', () => {
    expect( blockRegistry ).toContain( 'paycor-jobs' );
    expect( blockRegistry ).toEqual( [ ...blockRegistry ].sort() );
  } );

  it( 'does not reserve blank frontend height when the external embed is absent', () => {
    expect( frontendStyles ).not.toMatch( /min-height:\s*24rem;/ );
    expect( frontendStyles ).toContain(
      'margin-block-end: var(--celina-section-space, 4rem);'
    );
    expect( frontendStyles ).not.toMatch(
      /\.celina-paycor-jobs__embed\s+iframe/
    );
  } );

  it( 'styles the fallback link as a clear CTA with visible keyboard focus', () => {
    expect( frontendStyles ).toMatch(
      /\.celina-paycor-jobs__fallback a\s*\{[\s\S]*display:\s*inline-flex;[\s\S]*background:\s*var\(--celina-color-stone-dark, #2e3432\);/
    );
    expect( frontendStyles ).toMatch(
      /\.celina-paycor-jobs__fallback a:focus-visible\s*\{[\s\S]*outline:\s*3px solid var\(--celina-color-citrus, #ff6c0d\);/
    );
  } );

  it( 'exposes Paycor settings and saves all configured fields', () => {
    const setAttributes = vi.fn();

    render(
      createElement( PaycorEdit, {
        attributes: defaultAttributes,
        setAttributes,
      } )
    );

    expect(
      screen.getByRole( 'region', { name: 'Paycor settings' } )
    ).not.toBeNull();
    expect( screen.getByRole( 'status' ).textContent ).toContain(
      'Paycor jobs are not configured in this workbench.'
    );

    fireEvent.change( screen.getByRole( 'textbox', { name: 'Heading' } ), {
      target: { value: 'Join Celina' },
    } );
    fireEvent.change(
      screen.getByRole( 'textbox', { name: 'Paycor client ID' } ),
      { target: { value: '00000000000000000000000000000000' } }
    );
    fireEvent.change(
      screen.getByRole( 'textbox', { name: 'Fallback text' } ),
      { target: { value: 'See Paycor for current openings.' } }
    );
    fireEvent.change(
      screen.getByRole( 'textbox', { name: 'Direct jobs link text' } ),
      { target: { value: 'See jobs' } }
    );

    expect( setAttributes ).toHaveBeenNthCalledWith( 1, {
      heading: 'Join Celina',
    } );
    expect( setAttributes ).toHaveBeenNthCalledWith( 2, {
      clientId: '00000000000000000000000000000000',
    } );
    expect( setAttributes ).toHaveBeenNthCalledWith( 3, {
      fallbackText: 'See Paycor for current openings.',
    } );
    expect( setAttributes ).toHaveBeenNthCalledWith( 4, {
      jobsLinkText: 'See jobs',
    } );
  } );

  it( 'reports the configured state for a valid client ID', () => {
    render(
      createElement( PaycorEdit, {
        attributes: {
          ...defaultAttributes,
          clientId: '00000000000000000000000000000000',
        },
        setAttributes: vi.fn(),
      } )
    );

    expect( screen.getByRole( 'status' ).textContent ).toContain(
      'Paycor jobs are configured. The live list loads only on the frontend.'
    );
  } );
} );
