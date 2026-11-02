import { spawn } from 'node:child_process';
import type { ChildProcessWithoutNullStreams } from 'node:child_process';
import {
  link,
  mkdtemp,
  mkdir,
  readFile,
  readdir,
  realpath,
  rm,
  writeFile,
} from 'node:fs/promises';
import { hostname, tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { afterEach, describe, expect, it } from 'vitest';
import { withExclusiveBlockLock } from './lock.ts';

const roots: string[] = [];
const lockName = '.celina-block-create.lock';
const staleToken = '00000000-0000-4000-8000-000000000001';

function deferred< Value = void >(): {
  promise: Promise< Value >;
  resolve: ( value: Value ) => void;
} {
  let resolve!: ( value: Value ) => void;
  const promise = new Promise< Value >( ( done ) => {
    resolve = done;
  } );
  return { promise, resolve };
}

function owner( token: string, pid = 2_147_483_647 ) {
  return {
    version: 1,
    pid,
    hostname: hostname(),
    token,
    createdAt: '2000-01-01T00:00:00.000Z',
  };
}

function candidatePath(
  root: string,
  value: ReturnType< typeof owner >
): string {
  return join(
    root,
    `${ lockName }.${ value.pid }.${ value.token }.candidate`
  );
}

async function project( withWordPressFiles = false ): Promise< string > {
  const root = await mkdtemp( join( tmpdir(), 'celina-lock-' ) );
  roots.push( root );
  if ( withWordPressFiles ) {
    await mkdir( join( root, 'src/blocks' ), { recursive: true } );
    await writeFile( join( root, 'blocks.json' ), '[]\n' );
  }
  return realpath( root );
}

async function expectNoLockArtifacts( root: string ): Promise< void > {
  expect(
    ( await readdir( root ) ).filter( ( name ) => name.startsWith( lockName ) )
  ).toEqual( [] );
}

function waitForOutput(
  child: ChildProcessWithoutNullStreams,
  expected: string
): Promise< void > {
  return new Promise( ( resolve, reject ) => {
    let output = '';
    const onData = ( chunk: Buffer ) => {
      output += chunk.toString();
      if ( output.includes( expected ) ) {
        child.stdout.off( 'data', onData );
        resolve();
      }
    };
    child.stdout.on( 'data', onData );
    child.once( 'exit', ( code ) => {
      reject(
        new Error(
          `child exited with ${ String( code ) } before emitting ${ expected }`
        )
      );
    } );
  } );
}

function childExit(
  child: ChildProcessWithoutNullStreams
): Promise< { code: number | null; stderr: string } > {
  let stderr = '';
  child.stderr.on( 'data', ( chunk: Buffer ) => {
    stderr += chunk.toString();
  } );
  return new Promise( ( resolve ) => {
    child.once( 'exit', ( code ) => resolve( { code, stderr } ) );
  } );
}

function generatorChild(
  root: string,
  slug: string,
  holdBeforeRegistry: boolean
): ChildProcessWithoutNullStreams {
  const generatorUrl = pathToFileURL(
    join( process.cwd(), 'scripts/block-create/generator.ts' )
  ).href;
  const script = `
    import { rename } from 'node:fs/promises';
    import { join } from 'node:path';
    import { generateBlock } from ${ JSON.stringify( generatorUrl ) };
    const [root, slug, hold] = process.argv.slice(1);
    await generateBlock(
      { root, slug, title: slug, dynamic: false },
      {
        onLockContention: () => process.stdout.write('CONTENDED\\n'),
        rename: async (source, destination) => {
          if (hold === 'hold' && destination === join(root, 'blocks.json')) {
            process.stdout.write('HELD\\n');
            await new Promise((resolve) => process.stdin.once('data', resolve));
          }
          await rename(source, destination);
        }
      }
    );
  `;
  return spawn(
    process.execPath,
    [
      '--import',
      'tsx',
      '--input-type=module',
      '--eval',
      script,
      root,
      slug,
      holdBeforeRegistry ? 'hold' : 'run',
    ],
    { stdio: [ 'pipe', 'pipe', 'pipe' ] }
  );
}

afterEach( async () => {
  await Promise.all(
    roots
      .splice( 0 )
      .map( ( root ) => rm( root, { recursive: true, force: true } ) )
  );
} );

describe( 'cooperative block generator lock', () => {
  it( 'retries when the observed owner releases before candidate verification', async () => {
    const root = await project();
    const ownerEntered = deferred();
    const releaseOwner = deferred();
    const firstWaiterEvent = deferred< 'observed' | 'contended' >();
    const resumeWaiter = deferred();
    const ownerRun = withExclusiveBlockLock( root, {}, async () => {
      ownerEntered.resolve();
      await releaseOwner.promise;
    } );
    await ownerEntered.promise;

    const waiterRun = withExclusiveBlockLock(
      root,
      {
        afterPublishedOwnerRead: async () => {
          firstWaiterEvent.resolve( 'observed' );
          await resumeWaiter.promise;
        },
        onLockContention: () => firstWaiterEvent.resolve( 'contended' ),
      },
      async () => {}
    );
    const event = await firstWaiterEvent.promise;
    releaseOwner.resolve();
    await ownerRun;
    resumeWaiter.resolve();
    await waiterRun;

    expect( event ).toBe( 'observed' );
    await expectNoLockArtifacts( root );
  } );

  it( 'retries a mixed observation when another owner replaces the lock', async () => {
    const root = await project();
    const firstOwnerEntered = deferred();
    const releaseFirstOwner = deferred();
    const firstWaiterEvent = deferred< 'observed' | 'contended' >();
    const resumeWaiter = deferred();
    const replacementContended = deferred();
    let replacementPublished = false;
    let observationPaused = false;
    const firstOwnerRun = withExclusiveBlockLock( root, {}, async () => {
      firstOwnerEntered.resolve();
      await releaseFirstOwner.promise;
    } );
    await firstOwnerEntered.promise;

    const waiterRun = withExclusiveBlockLock(
      root,
      {
        afterPublishedOwnerRead: async () => {
          if ( observationPaused ) {
            return;
          }
          observationPaused = true;
          firstWaiterEvent.resolve( 'observed' );
          await resumeWaiter.promise;
        },
        onLockContention: () => {
          firstWaiterEvent.resolve( 'contended' );
          if ( replacementPublished ) {
            replacementContended.resolve();
          }
        },
      },
      async () => {}
    );
    const event = await firstWaiterEvent.promise;
    releaseFirstOwner.resolve();
    await firstOwnerRun;

    if ( event === 'observed' ) {
      const replacementEntered = deferred();
      const releaseReplacement = deferred();
      const replacementRun = withExclusiveBlockLock( root, {}, async () => {
        replacementEntered.resolve();
        await releaseReplacement.promise;
      } );
      await replacementEntered.promise;
      replacementPublished = true;
      resumeWaiter.resolve();
      await replacementContended.promise;
      releaseReplacement.resolve();
      await replacementRun;
    } else {
      resumeWaiter.resolve();
    }
    await waiterRun;

    expect( event ).toBe( 'observed' );
    await expectNoLockArtifacts( root );
  } );

  it( 'waits for a live owner before entering the critical section', async () => {
    const root = await project();
    const lockPath = join( root, lockName );
    const liveOwner = owner( staleToken, process.pid );
    const liveCandidate = candidatePath( root, liveOwner );
    await writeFile( liveCandidate, `${ JSON.stringify( liveOwner ) }\n` );
    await link( liveCandidate, lockPath );
    let entered = false;
    let contended = false;
    const pending = withExclusiveBlockLock(
      root,
      { onLockContention: () => ( contended = true ) },
      async () => {
        entered = true;
      }
    );

    while ( ! contended ) {
      await delay( 5 );
    }
    expect( entered ).toBe( false );
    await rm( lockPath );
    await rm( liveCandidate );
    await pending;

    expect( entered ).toBe( true );
    await expectNoLockArtifacts( root );
  } );

  it( 'reclaims a complete lock owned by a dead process', async () => {
    const root = await project();
    const lockPath = join( root, lockName );
    const staleOwner = owner( staleToken );
    const candidate = candidatePath( root, staleOwner );
    await writeFile( candidate, `${ JSON.stringify( staleOwner ) }\n` );
    await link( candidate, lockPath );
    let entered = false;

    await withExclusiveBlockLock( root, {}, async () => {
      entered = true;
    } );

    expect( entered ).toBe( true );
    await expectNoLockArtifacts( root );
  } );

  it.each( [
    [ 'malformed', 'not-json\n', 'invalid published lock record' ],
    [
      'ownerless',
      `${ JSON.stringify( owner( staleToken ) ) }\n`,
      'published lock candidate is missing',
    ],
  ] )(
    'rejects a %s published lock without deleting it',
    async ( _state, content, message ) => {
      const root = await project();
      const lockPath = join( root, lockName );
      await writeFile( lockPath, content );

      await expect(
        withExclusiveBlockLock( root, {}, async () => {} )
      ).rejects.toThrow( message );

      await expect( readFile( lockPath, 'utf8' ) ).resolves.toBe( content );
      await expect( readdir( root ) ).resolves.toEqual( [ lockName ] );
    }
  );

  it( 'completes a dead reclaim claim before electing the current caller', async () => {
    const root = await project();
    const lockPath = join( root, lockName );
    const staleOwner = owner( staleToken );
    const claimant = owner(
      '00000000-0000-4000-8000-000000000002',
      2_147_483_646
    );
    const staleCandidate = candidatePath( root, staleOwner );
    const claimantCandidate = candidatePath( root, claimant );
    await writeFile( staleCandidate, `${ JSON.stringify( staleOwner ) }\n` );
    await link( staleCandidate, lockPath );
    await writeFile( claimantCandidate, `${ JSON.stringify( claimant ) }\n` );
    await link( claimantCandidate, `${ lockPath }.claim.${ staleToken }` );

    await withExclusiveBlockLock( root, {}, async () => {} );

    await expectNoLockArtifacts( root );
  } );

  it( 'rejects a malformed reclaim claim without deleting diagnostic records', async () => {
    const root = await project();
    const lockPath = join( root, lockName );
    const staleOwner = owner( staleToken );
    const staleCandidate = candidatePath( root, staleOwner );
    const claim = `${ lockPath }.claim.${ staleToken }`;
    await writeFile( staleCandidate, `${ JSON.stringify( staleOwner ) }\n` );
    await link( staleCandidate, lockPath );
    await writeFile( claim, 'not-json\n' );

    await expect(
      withExclusiveBlockLock( root, {}, async () => {} )
    ).rejects.toThrow( 'invalid stale-lock claim record' );

    await expect( readFile( lockPath, 'utf8' ) ).resolves.toContain(
      staleToken
    );
    await expect( readFile( claim, 'utf8' ) ).resolves.toBe( 'not-json\n' );
    await expect( readdir( root ) ).resolves.toEqual(
      expect.arrayContaining( [
        lockName,
        `${ lockName }.claim.${ staleToken }`,
      ] )
    );
  } );

  it( 'serializes real generator processes without losing a registry entry', async () => {
    const root = await project( true );
    const first = generatorChild( root, 'process-alpha', true );
    const firstExit = childExit( first );
    await waitForOutput( first, 'HELD' );
    const second = generatorChild( root, 'process-beta', false );
    const secondExit = childExit( second );

    await waitForOutput( second, 'CONTENDED' );
    expect( second.exitCode ).toBeNull();
    first.stdin.end( '\n' );
    const [ firstResult, secondResult ] = await Promise.all( [
      firstExit,
      secondExit,
    ] );

    expect( firstResult ).toEqual( { code: 0, stderr: '' } );
    expect( secondResult ).toEqual( { code: 0, stderr: '' } );
    await expect(
      readFile( join( root, 'blocks.json' ), 'utf8' )
    ).resolves.toBe( '[\n  "process-alpha",\n  "process-beta"\n]\n' );
    await expectNoLockArtifacts( root );
  } );

  it( 'serializes 20 roots with five concurrent callers each', async () => {
    const stressRoots = await Promise.all(
      Array.from( { length: 20 }, () => project() )
    );

    await Promise.all(
      stressRoots.map( async ( root ) => {
        let active = 0;
        let completed = 0;
        await Promise.all(
          Array.from( { length: 5 }, () =>
            withExclusiveBlockLock( root, {}, async () => {
              expect( active ).toBe( 0 );
              active += 1;
              await delay( 0 );
              active -= 1;
              completed += 1;
            } )
          )
        );
        expect( completed ).toBe( 5 );
        await expectNoLockArtifacts( root );
      } )
    );
  } );

  it( 'surfaces a release failure with the remaining candidate path', async () => {
    const root = await project();
    let caught: unknown;

    try {
      await withExclusiveBlockLock(
        root,
        {
          remove: async ( path, options ) => {
            if ( String( path ).endsWith( '.candidate' ) ) {
              throw Object.assign( new Error( 'candidate cleanup denied' ), {
                code: 'EACCES',
              } );
            }
            await rm( path, options );
          },
        },
        async () => {}
      );
    } catch ( error ) {
      caught = error;
    }

    expect( caught ).toBeInstanceOf( AggregateError );
    expect(
      ( caught as AggregateError & { leftoverPaths: string[] } ).leftoverPaths
    ).toEqual( [ expect.stringMatching( /\.candidate$/ ) ] );
    const [ candidate ] = (
      caught as AggregateError & { leftoverPaths: string[] }
     ).leftoverPaths;
    if ( candidate ) {
      await rm( candidate, { force: true } );
    }
    await expectNoLockArtifacts( root );
  } );
} );
