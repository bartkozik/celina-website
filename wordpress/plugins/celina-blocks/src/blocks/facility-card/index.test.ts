import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import metadata from './block.json';
import {
  normalizeFacilityCardAttributes,
  type FacilityCardAttributes,
} from './model';

describe( 'Facility Card item', () => {
  it( 'is dynamic and can only be inserted inside Facility Cards', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/facility-card',
      version: '0.1.0',
      title: 'Facility Card — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'location-alt',
      textdomain: 'celina-blocks',
      parent: [ 'celina/facility-cards' ],
      attributes: {
        imageId: { type: 'number', default: 0 },
        imageUrl: { type: 'string', default: '' },
        imageAlt: { type: 'string', default: '' },
        name: { type: 'string', default: '' },
        address: { type: 'string', default: '' },
        phone: { type: 'string', default: '' },
        email: { type: 'string', default: '' },
        label: { type: 'string', default: '' },
        url: { type: 'string', default: '' },
      },
      supports: { html: false, reusable: false },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
      render: 'file:./render.php',
    } );
  } );

  it( 'normalizes malformed attributes without inventing content', () => {
    expect(
      normalizeFacilityCardAttributes( {
        imageId: -7.5,
        imageUrl: 42,
        imageAlt: 'Main plant',
        name: 'CELINA Ohio',
        address: '<p>123 Main Street</p>',
        phone: '+1 (555) 010-0000',
        email: 'contact@example.invalid',
        label: 'Directions',
        url: '/contact',
      } )
    ).toEqual( {
      imageId: 0,
      imageUrl: '',
      imageAlt: 'Main plant',
      name: 'CELINA Ohio',
      address: '<p>123 Main Street</p>',
      phone: '+1 (555) 010-0000',
      email: 'contact@example.invalid',
      label: 'Directions',
      url: '/contact',
    } satisfies FacilityCardAttributes );
  } );

  it( 'uses native media and URL controls with a dynamic save contract', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/facility-card/edit.tsx',
      'utf8'
    );
    const registration = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/facility-card/index.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'MediaUpload' );
    expect( editor ).toContain( 'MediaUploadCheck' );
    expect( editor ).toContain( 'URLInputButton' );
    expect( registration ).toContain( 'save: () => null' );
  } );
} );
