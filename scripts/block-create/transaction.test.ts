import {
  mkdtemp,
  mkdir,
  readFile,
  readdir,
  realpath,
  rename,
  rm,
  writeFile,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { generateBlock } from './generator.ts';
import {
  commitBlockTransaction,
  recoverBlockTransaction,
} from './transaction.ts';
import type { BlockTransactionJournal } from './transaction.ts';

const roots: string[] = [];
const journalName = '.celina-block-create.transaction.json';
const transactionId = '00000000-0000-4000-8000-000000000001';

function registry( slugs: string[] ): string {
  return `${ JSON.stringify( slugs, null, 2 ) }\n`;
}

function journal(
  slug: string,
  previousRegistry: string,
  overrides: Partial< BlockTransactionJournal > = {}
): BlockTransactionJournal {
  return {
    version: 1,
    slug,
    temporaryDirectory: `.${ slug }.${ transactionId }.stage`,
    temporaryRegistry: `.blocks.json.${ transactionId }.stage`,
    previousRegistry,
    nextRegistry: registry(
      [ ...( JSON.parse( previousRegistry ) as string[] ), slug ].sort()
    ),
    removeSourceIfEmpty: false,
    removeBlocksIfEmpty: false,
    ...overrides,
  };
}

async function project( parents = true ): Promise< string > {
  const root = await mkdtemp( join( tmpdir(), 'celina-transaction-' ) );
  roots.push( root );
  if ( parents ) {
    await mkdir( join( root, 'src/blocks' ), { recursive: true } );
  }
  await writeFile( join( root, 'blocks.json' ), '[]\n' );
  return realpath( root );
}

async function publishJournal(
  root: string,
  transaction: BlockTransactionJournal
): Promise< void > {
  await writeFile(
    join( root, journalName ),
    `${ JSON.stringify( transaction, null, 2 ) }\n`
  );
}

async function expectNoArtifacts( root: string ): Promise< void > {
  expect(
    ( await readdir( root ) ).filter(
      ( name ) =>
        name.startsWith( '.celina-block-create' ) ||
        name.startsWith( '.blocks.json.' )
    )
  ).toEqual( [] );
  try {
    expect(
      ( await readdir( join( root, 'src/blocks' ) ) ).filter( ( name ) =>
        name.startsWith( '.' )
      )
    ).toEqual( [] );
  } catch ( error ) {
    expect( error ).toMatchObject( { code: 'ENOENT' } );
  }
}

async function recoverThenStopAtCollision( root: string ): Promise< void > {
  await expect(
    generateBlock( {
      root,
      slug: 'existing',
      title: 'Existing',
      dynamic: false,
    } )
  ).rejects.toThrow( 'block existing already exists' );
}

afterEach( async () => {
  await Promise.all(
    roots
      .splice( 0 )
      .map( ( root ) => rm( root, { recursive: true, force: true } ) )
  );
} );

describe( 'block transaction recovery', () => {
  it( 'recovers a crash before creating the source parent', async () => {
    const root = await project( false );
    await writeFile( join( root, 'blocks.json' ), registry( [ 'existing' ] ) );
    await publishJournal(
      root,
      journal( 'interrupted', registry( [ 'existing' ] ), {
        removeSourceIfEmpty: true,
        removeBlocksIfEmpty: true,
      } )
    );

    await recoverThenStopAtCollision( root );

    await expect( readdir( root ) ).resolves.toEqual( [ 'blocks.json' ] );
  } );

  it( 'removes an intended source parent after a crash before blocks mkdir', async () => {
    const root = await project( false );
    await writeFile( join( root, 'blocks.json' ), registry( [ 'existing' ] ) );
    await publishJournal(
      root,
      journal( 'interrupted', registry( [ 'existing' ] ), {
        removeSourceIfEmpty: true,
        removeBlocksIfEmpty: true,
      } )
    );
    await mkdir( join( root, 'src' ) );

    await recoverThenStopAtCollision( root );

    await expect( readdir( root ) ).resolves.toEqual( [ 'blocks.json' ] );
  } );

  it( 'removes a partial stage and published journal after stage failure', async () => {
    const root = await project();
    const transaction = journal( 'partial-stage', '[]\n' );

    await expect(
      commitBlockTransaction(
        root,
        transaction,
        {},
        async ( paths, createTemporaryDirectory ) => {
          await createTemporaryDirectory();
          await writeFile(
            join( paths.temporaryDirectory, 'partial.txt' ),
            'partial'
          );
          throw new Error( 'partial stage failed' );
        }
      )
    ).rejects.toThrow( 'partial stage failed' );

    await expect(
      readFile( join( root, 'blocks.json' ), 'utf8' )
    ).resolves.toBe( '[]\n' );
    await expect( readdir( join( root, 'src/blocks' ) ) ).resolves.toEqual(
      []
    );
    await expectNoArtifacts( root );
  } );

  it( 'restores exact registry bytes and created parents after registry rename failure', async () => {
    const root = await project( false );
    const previousRegistry = '["existing"]\n';
    const registryPath = join( root, 'blocks.json' );
    await writeFile( registryPath, previousRegistry );

    await expect(
      generateBlock(
        {
          root,
          slug: 'rolled-back',
          title: 'Rolled Back',
          dynamic: false,
        },
        {
          rename: async ( source, destination ) => {
            if ( destination === registryPath ) {
              throw new Error( 'forced registry move failure' );
            }
            await rename( source, destination );
          },
        }
      )
    ).rejects.toThrow( 'forced registry move failure' );

    await expect( readFile( registryPath, 'utf8' ) ).resolves.toBe(
      previousRegistry
    );
    await expect( readdir( root ) ).resolves.toEqual( [ 'blocks.json' ] );
  } );

  it( 'rolls back an interruption after the directory rename', async () => {
    const root = await project();
    const transaction = journal( 'interrupted', '[]\n' );
    const destination = join( root, 'src/blocks/interrupted' );
    await mkdir( destination );
    await writeFile( join( destination, 'partial.txt' ), 'partial' );
    await writeFile(
      join( root, transaction.temporaryRegistry ),
      transaction.nextRegistry
    );
    await publishJournal( root, transaction );

    await recoverBlockTransaction( root );

    await expect(
      readFile( join( root, 'blocks.json' ), 'utf8' )
    ).resolves.toBe( '[]\n' );
    await expect( readdir( join( root, 'src/blocks' ) ) ).resolves.toEqual(
      []
    );
    await expectNoArtifacts( root );
  } );

  it( 'finalizes only destination plus the exact next registry', async () => {
    const root = await project();
    const transaction = journal( 'committed', '[]\n' );
    const destination = join( root, 'src/blocks/committed' );
    await mkdir( destination );
    await writeFile( join( destination, 'kept.txt' ), 'kept' );
    await writeFile( join( root, 'blocks.json' ), transaction.nextRegistry );
    await publishJournal( root, transaction );

    await recoverBlockTransaction( root );

    await expect(
      readFile( join( destination, 'kept.txt' ), 'utf8' )
    ).resolves.toBe( 'kept' );
    await expect(
      readFile( join( root, 'blocks.json' ), 'utf8' )
    ).resolves.toBe( transaction.nextRegistry );
    await expectNoArtifacts( root );
  } );

  it.each( [ 'missing', 'unexpected' ] )(
    'rejects a %s registry state and retains the journal for diagnosis',
    async ( registryState ) => {
      const root = await project();
      const transaction = journal( 'conflicted', '[]\n' );
      const unexpected = registry( [ 'external-change' ] );
      const destination = join( root, 'src/blocks/conflicted' );
      await mkdir( destination );
      await writeFile( join( destination, 'kept.txt' ), 'kept' );
      if ( registryState === 'missing' ) {
        await rm( join( root, 'blocks.json' ) );
      } else {
        await writeFile( join( root, 'blocks.json' ), unexpected );
      }
      await publishJournal( root, transaction );

      await expect( recoverBlockTransaction( root ) ).rejects.toThrow(
        'unexpected blocks.json state'
      );

      if ( registryState === 'missing' ) {
        await expect(
          readFile( join( root, 'blocks.json' ), 'utf8' )
        ).rejects.toMatchObject( { code: 'ENOENT' } );
      } else {
        await expect(
          readFile( join( root, 'blocks.json' ), 'utf8' )
        ).resolves.toBe( unexpected );
      }
      await expect(
        readFile( join( destination, 'kept.txt' ), 'utf8' )
      ).resolves.toBe( 'kept' );
      await expect(
        readFile( join( root, journalName ), 'utf8' )
      ).resolves.toContain( 'conflicted' );
    }
  );

  it( 'reports cleanup leftovers, retains recovery state, and recovers next time', async () => {
    const root = await project();
    const registryPath = join( root, 'blocks.json' );
    const destination = join( root, 'src/blocks/stuck' );
    let caught: unknown;

    try {
      await generateBlock(
        { root, slug: 'stuck', title: 'Stuck', dynamic: false },
        {
          rename: async ( source, target ) => {
            if ( target === registryPath ) {
              throw new Error( 'forced registry move failure' );
            }
            await rename( source, target );
          },
          remove: async ( path, options ) => {
            if ( String( path ) === destination ) {
              throw Object.assign( new Error( 'cleanup denied' ), {
                code: 'EACCES',
              } );
            }
            await rm( path, options );
          },
        }
      );
    } catch ( error ) {
      caught = error;
    }

    expect( caught ).toBeInstanceOf( AggregateError );
    expect(
      ( caught as AggregateError ).errors.map( ( error ) =>
        error instanceof Error ? error.message : String( error )
      )
    ).toEqual(
      expect.arrayContaining( [
        'forced registry move failure',
        'cleanup denied',
      ] )
    );
    expect(
      ( caught as AggregateError & { leftoverPaths: string[] } ).leftoverPaths
    ).toEqual(
      expect.arrayContaining( [ destination, join( root, journalName ) ] )
    );

    await generateBlock( {
      root,
      slug: 'recovered-cleanup',
      title: 'Recovered Cleanup',
      dynamic: false,
    } );

    await expect( readdir( join( root, 'src/blocks' ) ) ).resolves.toEqual( [
      'recovered-cleanup',
    ] );
    await expectNoArtifacts( root );
  } );

  it( 'rejects unsafe journal paths without touching derived locations', async () => {
    const sandbox = await mkdtemp( join( tmpdir(), 'celina-transaction-' ) );
    roots.push( sandbox );
    const rootPath = join( sandbox, 'plugin' );
    const outside = join( sandbox, 'outside' );
    await mkdir( join( rootPath, 'src/blocks' ), { recursive: true } );
    await mkdir( outside );
    await writeFile( join( outside, 'kept.txt' ), 'kept' );
    await writeFile( join( rootPath, 'blocks.json' ), '[]\n' );
    const root = await realpath( rootPath );
    await publishJournal( root, {
      ...journal( 'unsafe-journal', '[]\n' ),
      temporaryDirectory: '../../../outside',
    } );

    await expect( recoverBlockTransaction( root ) ).rejects.toThrow(
      'invalid block generator transaction journal'
    );

    await expect(
      readFile( join( outside, 'kept.txt' ), 'utf8' )
    ).resolves.toBe( 'kept' );
    await expect(
      readFile( join( root, journalName ), 'utf8' )
    ).resolves.toContain( '../../../outside' );
  } );

  it( 'rejects a reserved journal slug without touching its directory', async () => {
    const root = await project();
    const destination = join( root, 'src/blocks/core' );
    await mkdir( destination );
    await writeFile( join( destination, 'kept.txt' ), 'kept' );
    const unsafe = {
      ...journal( 'safe-name', '[]\n' ),
      slug: 'core',
      temporaryDirectory: `.core.${ transactionId }.stage`,
      nextRegistry: registry( [ 'core' ] ),
    };
    await publishJournal( root, unsafe );

    await expect( recoverBlockTransaction( root ) ).rejects.toThrow(
      'invalid block generator transaction journal'
    );

    await expect(
      readFile( join( destination, 'kept.txt' ), 'utf8' )
    ).resolves.toBe( 'kept' );
    await expect(
      readFile( join( root, journalName ), 'utf8' )
    ).resolves.toContain( '"slug": "core"' );
  } );
} );
