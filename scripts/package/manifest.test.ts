import { createHash } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { createSha256Manifest } from './manifest.ts';

describe( 'SHA-256 manifest', () => {
  it( 'writes sorted lower-case digests using the standard checksum format', () => {
    const theme = new TextEncoder().encode( 'theme' );
    const plugin = new TextEncoder().encode( 'plugin' );
    const digest = ( bytes: Uint8Array ) =>
      createHash( 'sha256' ).update( bytes ).digest( 'hex' );

    expect(
      createSha256Manifest( [
        { bytes: theme, fileName: 'celina-child.zip' },
        { bytes: plugin, fileName: 'celina-blocks.zip' },
      ] )
    ).toBe(
      `${ digest( plugin ) }  celina-blocks.zip\n${ digest(
        theme
      ) }  celina-child.zip\n`
    );
  } );

  it( 'rejects unsafe artifact names', () => {
    expect( () =>
      createSha256Manifest( [
        {
          bytes: new Uint8Array(),
          fileName: '../celina-child.zip',
        },
      ] )
    ).toThrow( 'artifact name' );
  } );
} );
