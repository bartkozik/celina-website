import { lstat, readFile, readdir } from 'node:fs/promises';
import { isAbsolute, relative, resolve, sep } from 'node:path';
import { zipSync, type Zippable } from 'fflate';

export interface ArchiveOptions {
  includes: string[];
  root: string;
  topLevel: 'celina-blocks' | 'celina-child';
}

interface ArchiveEntry {
  bytes: Uint8Array;
  relativePath: string;
}

// ZIP timestamps have no time-zone field. Construct local midnight so every
// builder serializes the same DOS date/time bytes regardless of its time zone.
const fixedMtime = new Date( 1980, 0, 1, 0, 0, 0 );
// ZIP stores Unix permissions in the upper 16 bits: 0o644 * 2^16.
const normalizedFileAttributes = 27_525_120;
const excludedDirectories = new Set( [
  '.git',
  'node_modules',
  'playwright-report',
  'test-results',
  'tests',
  'vendor',
] );

function comparePaths( left: string, right: string ): number {
  return Buffer.from( left ).compare( Buffer.from( right ) );
}

function validateInclude( include: string ): string {
  const normalized = include.replaceAll( '\\', '/' );
  const segments = normalized.split( '/' );

  if (
    ! include ||
    isAbsolute( include ) ||
    normalized.startsWith( '/' ) ||
    segments.includes( '..' )
  ) {
    throw new Error( `Archive include path escape: ${ include }` );
  }

  return normalized;
}

function isExcluded( relativePath: string ): boolean {
  const segments = relativePath.split( '/' );
  return (
    segments.some( ( segment ) => excludedDirectories.has( segment ) ) ||
    segments.at( -1 ) === '.DS_Store' ||
    relativePath.endsWith( '.map' )
  );
}

async function collectEntries(
  options: ArchiveOptions
): Promise< ArchiveEntry[] > {
  if ( ! /^celina-(?:blocks|child)$/.test( options.topLevel ) ) {
    throw new Error(
      `Invalid archive top-level directory: ${ options.topLevel }`
    );
  }

  const root = resolve( options.root );
  const entries = new Map< string, ArchiveEntry >();

  const visit = async ( absolutePath: string ): Promise< void > => {
    const stats = await lstat( absolutePath );
    const relativePath = relative( root, absolutePath )
      .split( sep )
      .join( '/' );

    if ( stats.isSymbolicLink() ) {
      throw new Error(
        `Archive symbolic link is not allowed: ${ relativePath || '.' }`
      );
    }
    if ( relativePath && isExcluded( relativePath ) ) {
      return;
    }
    if ( stats.isDirectory() ) {
      const children = await readdir( absolutePath );
      children.sort( comparePaths );
      for ( const child of children ) {
        await visit( resolve( absolutePath, child ) );
      }
      return;
    }
    if ( ! stats.isFile() ) {
      throw new Error( `Unsupported archive entry: ${ relativePath }` );
    }
    if ( entries.has( relativePath ) ) {
      throw new Error( `Duplicate archive entry: ${ relativePath }` );
    }

    entries.set( relativePath, {
      bytes: await readFile( absolutePath ),
      relativePath,
    } );
  };

  for ( const requested of options.includes ) {
    const include = validateInclude( requested );
    const absolutePath = include === '.' ? root : resolve( root, include );
    const fromRoot = relative( root, absolutePath );

    if ( fromRoot === '..' || fromRoot.startsWith( `..${ sep }` ) ) {
      throw new Error( `Archive include path escape: ${ requested }` );
    }
    await visit( absolutePath );
  }

  return [ ...entries.values() ].sort( ( left, right ) =>
    comparePaths( left.relativePath, right.relativePath )
  );
}

export async function createDeterministicArchive(
  options: ArchiveOptions
): Promise< Uint8Array > {
  const files: Zippable = Object.create( null ) as Zippable;

  for ( const entry of await collectEntries( options ) ) {
    files[ `${ options.topLevel }/${ entry.relativePath }` ] = [
      entry.bytes,
      {
        attrs: normalizedFileAttributes,
        level: 9,
        mtime: fixedMtime,
        os: 3,
      },
    ];
  }

  return zipSync( files, {
    attrs: normalizedFileAttributes,
    level: 9,
    mtime: fixedMtime,
    os: 3,
  } );
}
