// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { createElement, type ReactElement } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import Edit from './edit';
import { isHubSpotConfigurationValid, type HubSpotAttributes } from './model';

const blockRegistry = vi.hoisted( () => ( {
  registerBlockType: vi.fn(),
  registerBlockVariation: vi.fn(),
} ) );

vi.mock( '@wordpress/blocks', () => blockRegistry );

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );

  return {
    InspectorControls: ( { children }: { children?: React.ReactNode } ) =>
      React.createElement( 'aside', null, children ),
    RichText: ( {
      onChange,
      value = '',
    }: {
      onChange?: ( value: string ) => void;
      value?: string;
    } ) =>
      React.createElement( 'input', {
        'aria-label': 'Generated content',
        onChange: ( event: React.ChangeEvent< HTMLInputElement > ) =>
          onChange?.( event.target.value ),
        value,
      } ),
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
    SelectControl: ( {
      label,
      onChange,
      options,
      value,
    }: {
      label: string;
      onChange: ( value: string ) => void;
      options: Array< { label: string; value: string } >;
      value: string;
    } ) =>
      React.createElement(
        'label',
        null,
        label,
        React.createElement(
          'select',
          {
            'aria-label': label,
            onChange: ( event: React.ChangeEvent< HTMLSelectElement > ) =>
              onChange( event.target.value ),
            value,
          },
          options.map( ( option ) =>
            React.createElement(
              'option',
              { key: option.value, value: option.value },
              option.label
            )
          )
        )
      ),
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

const defaultAttributes: HubSpotAttributes = {
  heading: 'Contact our team',
  region: 'na1',
  portalId: '',
  formId: '',
  fallbackText: 'Contact us directly if the form is unavailable.',
};

type HubSpotEditProps = {
  attributes: typeof defaultAttributes;
  setAttributes: ( next: Partial< typeof defaultAttributes > ) => void;
};

const HubSpotEdit = Edit as unknown as (
  props: HubSpotEditProps
) => ReactElement;

describe( 'HubSpot Form', () => {
  afterEach( cleanup );

  it( 'registers the reusable form presets in the block inserter', async () => {
    blockRegistry.registerBlockType.mockClear();
    blockRegistry.registerBlockVariation.mockClear();

    await import( './index' );

    expect( blockRegistry.registerBlockVariation ).toHaveBeenCalledTimes( 4 );
    expect( blockRegistry.registerBlockVariation.mock.calls ).toEqual( [
      [
        'celina/hubspot-form',
        expect.objectContaining( {
          name: 'request-callback',
          title: 'Request a callback',
          scope: [ 'inserter' ],
          attributes: {
            heading: 'Request a Callback',
            region: 'na1',
            portalId: 'portfolio-unconfigured',
            formId: '',
            fallbackText:
              'If the form is unavailable, email sales@example.invalid or call (202) 555-0100.',
          },
        } ),
      ],
      [
        'celina/hubspot-form',
        expect.objectContaining( {
          name: 'ask-question',
          title: 'Ask us a question',
          scope: [ 'inserter' ],
          attributes: {
            heading: 'Ask the CELINA Team',
            region: 'na1',
            portalId: 'portfolio-unconfigured',
            formId: '',
            fallbackText:
              'If the form is unavailable, email sales@example.invalid or call (202) 555-0100.',
          },
        } ),
      ],
      [
        'celina/hubspot-form',
        expect.objectContaining( {
          name: 'request-quote',
          title: 'Request a quote',
          scope: [ 'inserter' ],
          attributes: {
            heading: 'Request a Quote',
            region: 'na1',
            portalId: 'portfolio-unconfigured',
            formId: '',
            fallbackText:
              'If the form is unavailable, email sales@example.invalid or call (202) 555-0100.',
          },
        } ),
      ],
      [
        'celina/hubspot-form',
        expect.objectContaining( {
          name: 'technical-inquiry',
          title: 'Product or technical inquiry',
          scope: [ 'inserter' ],
          attributes: {
            heading: 'Ask a Product or Technical Question',
            region: 'na1',
            portalId: 'portfolio-unconfigured',
            formId: '',
            fallbackText:
              'If the form is unavailable, email sales@example.invalid or call (202) 555-0100.',
          },
        } ),
      ],
    ] );
  } );

  it( 'declares the allowlisted dynamic block contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/hubspot-form',
      version: '0.1.0',
      title: 'HubSpot Form — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'feedback',
      textdomain: 'celina-blocks',
      attributes: {
        heading: { type: 'string', default: 'Contact our team' },
        region: {
          type: 'string',
          enum: [ 'na1', 'eu1' ],
          default: 'na1',
        },
        portalId: { type: 'string', default: '' },
        formId: { type: 'string', default: '' },
        fallbackText: {
          type: 'string',
          default: 'Contact us directly if the form is unavailable.',
        },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
      render: 'file:./render.php',
    } );
  } );

  it( 'shows explicit settings and an honest unconfigured preview', () => {
    const setAttributes = vi.fn();

    render(
      createElement( HubSpotEdit, {
        attributes: defaultAttributes,
        setAttributes,
      } )
    );

    expect(
      screen.getByRole( 'region', { name: 'HubSpot settings' } )
    ).not.toBeNull();
    expect( screen.getByRole( 'combobox', { name: 'Region' } ) ).not.toBeNull();
    expect(
      screen.getByRole( 'textbox', { name: 'Portal ID' } )
    ).not.toBeNull();
    expect( screen.getByRole( 'textbox', { name: 'Form ID' } ) ).not.toBeNull();
    expect( screen.getByRole( 'status' ).textContent ).toContain(
      'HubSpot form is not configured in this workbench.'
    );

    fireEvent.change( screen.getByRole( 'textbox', { name: 'Portal ID' } ), {
      target: { value: '123456' },
    } );
    expect( setAttributes ).toHaveBeenCalledWith( { portalId: '123456' } );
  } );

  it( 'reports a configured preview only for allowlisted valid identifiers', () => {
    const { rerender } = render(
      createElement( HubSpotEdit, {
        attributes: {
          ...defaultAttributes,
          region: 'eu1',
          portalId: '123456',
          formId: '123e4567-e89b-12d3-a456-426614174000',
        },
        setAttributes: vi.fn(),
      } )
    );

    expect( screen.getByRole( 'status' ).textContent ).toContain(
      'HubSpot form is configured.'
    );

    rerender(
      createElement( HubSpotEdit, {
        attributes: {
          ...defaultAttributes,
          region: 'ap1' as 'na1',
          portalId: 'not-decimal',
          formId: 'not-a-uuid',
        },
        setAttributes: vi.fn(),
      } )
    );

    expect( screen.getByRole( 'status' ).textContent ).toContain(
      'HubSpot form is not configured in this workbench.'
    );
  } );

  it( 'rejects identifiers with trailing whitespace or line breaks', () => {
    expect(
      isHubSpotConfigurationValid( {
        ...defaultAttributes,
        portalId: '123456\n',
        formId: '123e4567-e89b-12d3-a456-426614174000',
      } )
    ).toBe( false );
    expect(
      isHubSpotConfigurationValid( {
        ...defaultAttributes,
        portalId: '123456',
        formId: '123e4567-e89b-12d3-a456-426614174000 ',
      } )
    ).toBe( false );
  } );
} );
