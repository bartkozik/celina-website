import { describe, expect, it } from 'vitest';
import { EDITORIAL_VARIATIONS } from './editorial-variations';
import { ALLOWED_ARTICLE_BLOCKS } from './model';

describe( 'editable editorial starters', () => {
  it( 'makes the four requested native starters discoverable and unlocked', () => {
    expect(
      EDITORIAL_VARIATIONS.map( ( variation ) => variation.title )
    ).toEqual( [
      'CELINA Pro tip',
      'CELINA Inline CTA',
      'CELINA Attributed quote',
      'CELINA Author bio',
    ] );
    expect( ALLOWED_ARTICLE_BLOCKS ).toContain( 'core/group' );
    for ( const variation of EDITORIAL_VARIATIONS ) {
      expect( variation.category ).toBe( 'celina' );
      expect( variation.scope ).toContain( 'inserter' );
      expect( variation.attributes?.templateLock ).toBeUndefined();
      expect( variation.attributes?.lock ).toBeUndefined();
      expect( variation.attributes?.metadata ).toHaveProperty( 'name' );
      expect( variation.innerBlocks?.length ).toBeGreaterThan( 0 );
    }
  } );
} );
