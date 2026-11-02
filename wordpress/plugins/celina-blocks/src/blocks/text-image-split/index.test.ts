import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import metadata from './block.json';
import {
  normalizeImageId,
  normalizeTextImageAttributes,
  type TextImageAttributes,
} from './model';

describe( 'Text + Image Split', () => {
  it( 'declares the complete dynamic block contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/text-image-split',
      version: '0.1.0',
      title: 'Text + Image Split — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'align-pull-left',
      textdomain: 'celina-blocks',
      attributes: {
        eyebrow: { type: 'string', default: '' },
        heading: { type: 'string', default: '' },
        content: { type: 'string', default: '' },
        imageId: { type: 'number', default: 0 },
        imageUrl: { type: 'string', default: '' },
        imageAlt: { type: 'string', default: '' },
        imageDecorative: { type: 'boolean', default: false },
        imagePosition: {
          type: 'string',
          enum: [ 'left', 'right' ],
          default: 'right',
        },
        background: {
          type: 'string',
          enum: [ 'neutral', 'warm' ],
          default: 'neutral',
        },
        buttonLabel: { type: 'string', default: '' },
        buttonUrl: { type: 'string', default: '' },
        buttonNewTab: { type: 'boolean', default: false },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
      render: 'file:./render.php',
    } );
  } );

  it.each( [
    [ 12, 12 ],
    [ 12.8, 12 ],
    [ 0, 0 ],
    [ -2, 0 ],
    [ Number.NaN, 0 ],
  ] )( 'normalizes image ID %s to %s', ( value, expected ) => {
    expect( normalizeImageId( value ) ).toBe( expected );
  } );

  it( 'allowlists variants and coerces malformed values to safe defaults', () => {
    const normalized = normalizeTextImageAttributes( {
      eyebrow: 'Mission ready',
      heading: 'Rapidly deployable shelters',
      content: '<p>Configured for the field.</p>',
      imageId: -7,
      imageUrl: 'https://example.test/shelter.jpg',
      imageAlt: 'A deployed shelter',
      imageDecorative: true,
      imagePosition: 'center',
      background: 'black',
      buttonLabel: 'Explore',
      buttonUrl: '/capabilities',
      buttonNewTab: true,
    } );

    expect( normalized ).toEqual( {
      eyebrow: 'Mission ready',
      heading: 'Rapidly deployable shelters',
      content: '<p>Configured for the field.</p>',
      imageId: 0,
      imageUrl: 'https://example.test/shelter.jpg',
      imageAlt: 'A deployed shelter',
      imageDecorative: true,
      imagePosition: 'right',
      background: 'neutral',
      buttonLabel: 'Explore',
      buttonUrl: '/capabilities',
      buttonNewTab: true,
    } satisfies TextImageAttributes );
  } );

  it( 'uses native media controls and keeps the dynamic save contract', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/text-image-split/edit.tsx',
      'utf8'
    );
    const registration = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/text-image-split/index.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'MediaUpload' );
    expect( editor ).toContain( 'MediaUploadCheck' );
    expect( editor ).toContain( 'SelectControl' );
    expect( editor ).toContain( 'ToggleControl' );
    expect( editor ).toContain( 'URLInputButton' );
    expect( editor ).toContain( 'className="celina-text-image-split__prose"' );
    expect( registration ).toContain( 'save: () => null' );
  } );
} );
