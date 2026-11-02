// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/react';
import { createElement, type ReactElement } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import Edit from './edit';

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

const defaultAttributes = {
  heading: 'Current openings',
  companySubdomain: '',
  fallbackText: 'Job listings will appear here once BambooHR is connected.',
  jobsLinkText: 'View open positions',
};

type BambooHrEditProps = {
  attributes: typeof defaultAttributes;
  setAttributes: ( next: Partial< typeof defaultAttributes > ) => void;
};

const BambooHrEdit = Edit as unknown as (
  props: BambooHrEditProps
) => ReactElement;

describe( 'BambooHR Jobs', () => {
  afterEach( cleanup );

  it( 'keeps the legacy name and attributes while hiding the retired block', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/bamboohr-jobs',
      version: '0.1.0',
      title: 'Legacy BambooHR Jobs',
      category: 'celina',
      icon: 'businessperson',
      textdomain: 'celina-blocks',
      attributes: {
        heading: { type: 'string', default: 'Current openings' },
        companySubdomain: { type: 'string', default: '' },
        fallbackText: {
          type: 'string',
          default: 'Job listings will appear here once BambooHR is connected.',
        },
        jobsLinkText: { type: 'string', default: 'View open positions' },
      },
      supports: { html: false, anchor: true, inserter: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
      render: 'file:./render.php',
    } );
  } );

  it( 'identifies the legacy block and guides editors to Paycor without account controls', () => {
    const setAttributes = vi.fn();

    render(
      createElement( BambooHrEdit, {
        attributes: defaultAttributes,
        setAttributes,
      } )
    );

    expect( screen.getByText( 'Legacy BambooHR Jobs' ) ).not.toBeNull();
    expect(
      screen.getAllByText( 'Replace this legacy block with Paycor Jobs.' )
    ).toHaveLength( 2 );
    expect(
      screen.queryByRole( 'textbox', { name: 'Company subdomain' } )
    ).toBeNull();
    expect(
      screen.queryByRole( 'textbox', { name: 'Direct jobs link text' } )
    ).toBeNull();
    expect( setAttributes ).not.toHaveBeenCalled();
  } );
} );
