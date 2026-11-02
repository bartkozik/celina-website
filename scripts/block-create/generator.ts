import { randomUUID } from 'node:crypto';
import { access, mkdir, readFile, realpath, writeFile } from 'node:fs/promises';
import { isAbsolute, join, relative, resolve } from 'node:path';
import { withExclusiveBlockLock } from './lock.ts';
import type { LockOperations } from './lock.ts';
import { templates } from './templates.ts';
import {
  commitBlockTransaction,
  recoverBlockTransaction,
} from './transaction.ts';
import type {
  BlockTransactionJournal,
  TransactionOperations,
} from './transaction.ts';

const reserved = new Set( [ 'core', 'wordpress', 'wp', 'build', 'src' ] );
const safeSlug = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;

export interface GenerateInput {
  root: string;
  slug: string;
  title: string;
  dynamic: boolean;
}

export interface GenerateOperations
  extends TransactionOperations,
    LockOperations {}

function isInside( path: string, root: string ): boolean {
  const pathFromRoot = relative( root, path );
  return ! pathFromRoot.startsWith( '..' ) && ! isAbsolute( pathFromRoot );
}

async function pathExists( path: string ): Promise< boolean > {
  try {
    await access( path );
    return true;
  } catch ( error ) {
    if ( ( error as NodeJS.ErrnoException ).code === 'ENOENT' ) {
      return false;
    }
    throw error;
  }
}

async function assertCanonicalDirectory(
  path: string,
  root: string
): Promise< void > {
  const canonical = await realpath( path );
  if ( canonical !== path || ! isInside( canonical, root ) ) {
    throw new Error( 'block source directory resolves outside plugin root' );
  }
}

function parseRegistry( content: string ): string[] {
  const registry = JSON.parse( content ) as unknown;
  if (
    ! Array.isArray( registry ) ||
    ! registry.every( ( slug ) => typeof slug === 'string' )
  ) {
    throw new Error( 'blocks registry must be an array of slugs' );
  }
  return registry;
}

async function generateUnderLock(
  root: string,
  input: GenerateInput,
  operations: GenerateOperations
): Promise< void > {
  const registryPath = join( root, 'blocks.json' );
  const canonicalRegistry = await realpath( registryPath );
  if (
    canonicalRegistry !== registryPath ||
    ! isInside( canonicalRegistry, root )
  ) {
    throw new Error( 'blocks registry resolves outside plugin root' );
  }

  const previousRegistry = await readFile( registryPath, 'utf8' );
  const registry = parseRegistry( previousRegistry );
  if ( registry.includes( input.slug ) ) {
    throw new Error( `block ${ input.slug } already exists` );
  }
  const nextRegistry = `${ JSON.stringify(
    [ ...registry, input.slug ].sort(),
    null,
    2
  ) }\n`;

  const sourcePath = join( root, 'src' );
  const createdSource = ! ( await pathExists( sourcePath ) );
  if ( ! createdSource ) {
    await assertCanonicalDirectory( sourcePath, root );
  }

  const blocksPath = join( sourcePath, 'blocks' );
  const createdBlocks = createdSource || ! ( await pathExists( blocksPath ) );
  if ( ! createdBlocks ) {
    await assertCanonicalDirectory( blocksPath, root );
  }

  const destination = resolve( blocksPath, input.slug );
  if (
    ! isInside( destination, blocksPath ) ||
    ! isInside( destination, root )
  ) {
    throw new Error( `invalid block destination: ${ input.slug }` );
  }
  if ( ! createdBlocks && ( await pathExists( destination ) ) ) {
    throw new Error( `block ${ input.slug } already exists` );
  }

  const transactionId = randomUUID();
  const journal: BlockTransactionJournal = {
    version: 1,
    slug: input.slug,
    temporaryDirectory: `.${ input.slug }.${ transactionId }.stage`,
    temporaryRegistry: `.blocks.json.${ transactionId }.stage`,
    previousRegistry,
    nextRegistry,
    removeSourceIfEmpty: createdSource,
    removeBlocksIfEmpty: createdBlocks,
  };

  await commitBlockTransaction(
    root,
    journal,
    operations,
    async ( paths, createTemporaryDirectory ) => {
      if ( createdSource ) {
        await mkdir( paths.source );
      }
      await assertCanonicalDirectory( paths.source, root );

      if ( createdBlocks ) {
        await mkdir( paths.blocks );
      }
      await assertCanonicalDirectory( paths.blocks, root );

      await createTemporaryDirectory();
      for ( const [ name, content ] of Object.entries( templates( input ) ) ) {
        await writeFile( join( paths.temporaryDirectory, name ), content, {
          flag: 'wx',
        } );
      }
      await writeFile( paths.registryTemporary, nextRegistry, { flag: 'wx' } );
    }
  );
}

export async function generateBlock(
  input: GenerateInput,
  operations: GenerateOperations = {}
): Promise< void > {
  if ( ! safeSlug.test( input.slug ) || reserved.has( input.slug ) ) {
    throw new Error( `invalid block slug: ${ input.slug }` );
  }
  if ( ! input.title.trim() ) {
    throw new Error( 'block title is required' );
  }

  const root = await realpath( resolve( input.root ) );
  await withExclusiveBlockLock( root, operations, async () => {
    await recoverBlockTransaction( root, operations );
    await generateUnderLock( root, input, operations );
  } );
}
