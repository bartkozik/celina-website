import {
  chmod,
  mkdir,
  mkdtemp,
  rm,
  symlink,
  utimes,
  writeFile,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { unzipSync } from 'fflate';
import { afterEach, describe, expect, it } from 'vitest';
import { createDeterministicArchive } from './archive.ts';

const temporaryDirectories: string[] = [];

async function temporaryRoot(): Promise< string > {
  const root = await mkdtemp( join( tmpdir(), 'celina-package-' ) );
  temporaryDirectories.push( root );
  return root;
}

afterEach( async () => {
  await Promise.all(
    temporaryDirectories
      .splice( 0 )
      .map( ( directory ) => rm( directory, { force: true, recursive: true } ) )
  );
} );

describe( 'deterministic archive', () => {
  it( 'writes sorted POSIX paths under one top-level directory', async () => {
    const root = await temporaryRoot();
    await mkdir( join( root, 'nested' ), { recursive: true } );
    await writeFile( join( root, 'z.php' ), '<?php\n' );
    await writeFile( join( root, 'nested', 'a.css' ), 'body {}\n' );

    const archive = await createDeterministicArchive( {
      includes: [ '.' ],
      root,
      topLevel: 'celina-child',
    } );
    const paths = Object.keys( unzipSync( archive ) );
    const header = new DataView(
      archive.buffer,
      archive.byteOffset,
      archive.byteLength
    );

    expect( paths ).toEqual( [
      'celina-child/nested/a.css',
      'celina-child/z.php',
    ] );
    expect( paths.every( ( path ) => ! path.includes( '\\' ) ) ).toBe( true );
    expect( header.getUint16( 10, true ) ).toBe( 0 );
    expect( header.getUint16( 12, true ) ).toBe( 33 );
  } );

  it( 'produces identical bytes despite source mtimes and modes', async () => {
    const root = await temporaryRoot();
    const file = join( root, 'style.css' );
    await writeFile( file, '/* CELINA */\n' );

    const first = await createDeterministicArchive( {
      includes: [ '.' ],
      root,
      topLevel: 'celina-child',
    } );
    await utimes( file, new Date( '2026-01-01T12:00:00Z' ), new Date() );
    await chmod( file, 0o600 );
    const second = await createDeterministicArchive( {
      includes: [ '.' ],
      root,
      topLevel: 'celina-child',
    } );

    expect( second ).toEqual( first );
  } );

  it( 'excludes development-only files from a runtime tree', async () => {
    const root = await temporaryRoot();
    for ( const directory of [ 'tests', 'vendor', 'node_modules' ] ) {
      await mkdir( join( root, directory ), { recursive: true } );
      await writeFile( join( root, directory, 'private.php' ), 'excluded\n' );
    }
    await mkdir( join( root, 'assets' ), { recursive: true } );
    await writeFile( join( root, 'assets', 'app.js' ), 'runtime\n' );
    await writeFile( join( root, 'assets', 'app.js.map' ), '{}\n' );

    const paths = Object.keys(
      unzipSync(
        await createDeterministicArchive( {
          includes: [ '.' ],
          root,
          topLevel: 'celina-blocks',
        } )
      )
    );

    expect( paths ).toEqual( [ 'celina-blocks/assets/app.js' ] );
  } );

  it( 'rejects path escape and invalid package roots', async () => {
    const root = await temporaryRoot();

    await expect(
      createDeterministicArchive( {
        includes: [ '../escape' ],
        root,
        topLevel: 'celina-child',
      } )
    ).rejects.toThrow( 'path escape' );
    await expect(
      createDeterministicArchive( {
        includes: [ '.' ],
        root,
        topLevel: '../celina-child' as 'celina-child',
      } )
    ).rejects.toThrow( 'top-level' );
  } );

  it.skipIf( process.platform === 'win32' )(
    'rejects symbolic links in included paths',
    async () => {
      const root = await temporaryRoot();
      const target = join( root, 'target.txt' );
      await writeFile( target, 'target\n' );
      await symlink( target, join( root, 'linked.txt' ) );

      await expect(
        createDeterministicArchive( {
          includes: [ '.' ],
          root,
          topLevel: 'celina-child',
        } )
      ).rejects.toThrow( 'symbolic link' );
    }
  );
} );
