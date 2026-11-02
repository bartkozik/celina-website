import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import metadata from './block.json';
import {
  normalizeIndustryCardAttributes,
  type IndustryCardAttributes,
} from './model';

describe( 'Industry Card item', () => {
  it( 'is dynamic and can only be inserted inside Industry Cards', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/industry-card',
      version: '0.1.0',
      title: 'Industry Card — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'format-image',
      textdomain: 'celina-blocks',
      parent: [ 'celina/industry-cards' ],
      attributes: {
        imageId: { type: 'number', default: 0 },
        imageUrl: { type: 'string', default: '' },
        imageAlt: { type: 'string', default: '' },
        title: { type: 'string', default: '' },
        description: { type: 'string', default: '' },
        linkLabel: { type: 'string', default: '' },
        linkUrl: { type: 'string', default: '' },
        newTab: { type: 'boolean', default: false },
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
      normalizeIndustryCardAttributes( {
        imageId: -4.8,
        imageUrl: 42,
        imageAlt: 'Field shelter',
        title: 'Defense',
        description: '<p>Deployable systems.</p>',
        linkLabel: 'Explore',
        linkUrl: '/markets/defense',
        newTab: true,
      } )
    ).toEqual( {
      imageId: 0,
      imageUrl: '',
      imageAlt: 'Field shelter',
      title: 'Defense',
      description: '<p>Deployable systems.</p>',
      linkLabel: 'Explore',
      linkUrl: '/markets/defense',
      newTab: true,
    } satisfies IndustryCardAttributes );
  } );

  it( 'uses native media and URL controls with a dynamic save contract', () => {
    const editor = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/industry-card/edit.tsx',
      'utf8'
    );
    const registration = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/industry-card/index.tsx',
      'utf8'
    );

    expect( editor ).toContain( 'MediaUpload' );
    expect( editor ).toContain( 'MediaUploadCheck' );
    expect( editor ).toContain( 'URLInputButton' );
    expect( editor ).toContain( 'ToggleControl' );
    expect( registration ).toContain( 'save: () => null' );
  } );

  it( 'keeps card links aligned at the bottom of equal-height rows', () => {
    const styles = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/industry-card/style.scss',
      'utf8'
    );

    expect( styles ).toContain( 'display: flex;' );
    expect( styles ).toContain( 'flex-direction: column;' );
    expect( styles ).toContain( 'flex: 1;' );
    expect( styles ).toContain( 'margin-top: auto;' );
    expect( styles ).toContain( 'padding-top: var(--celina-space-6, 1.5rem);' );
  } );
} );
