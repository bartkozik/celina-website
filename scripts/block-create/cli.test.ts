import { execFile } from 'node:child_process';
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { afterEach, describe, expect, it } from 'vitest';
import { parseArguments, runCli } from './cli.ts';

const roots: string[] = [];
const cliPath = fileURLToPath( new URL( './cli.ts', import.meta.url ) );
const tsxImport = pathToFileURL(
  createRequire( import.meta.url ).resolve( 'tsx' )
).href;

interface ProcessResult {
  status: number;
  stderr: string;
  stdout: string;
}

async function cliProject( registry = '[]\n' ): Promise< string > {
  const root = await mkdtemp( join( tmpdir(), 'celina-cli-' ) );
  roots.push( root );
  const pluginRoot = join( root, 'wordpress/plugins/celina-blocks' );
  await mkdir( join( pluginRoot, 'src/blocks' ), { recursive: true } );
  await writeFile( join( pluginRoot, 'blocks.json' ), registry );
  return root;
}

function spawnCli( cwd: string, args: string[] ): Promise< ProcessResult > {
  return new Promise( ( resolve ) => {
    execFile(
      process.execPath,
      [ '--import', tsxImport, cliPath, ...args ],
      { cwd },
      ( error, stdout, stderr ) => {
        resolve( {
          status: error && typeof error.code === 'number' ? error.code : 0,
          stderr,
          stdout,
        } );
      }
    );
  } );
}

afterEach( async () => {
  await Promise.all(
    roots
      .splice( 0 )
      .map( ( root ) => rm( root, { recursive: true, force: true } ) )
  );
} );

describe( 'parseArguments', () => {
  it( 'accepts pnpm forwarded arguments with a leading separator', () => {
    expect( parseArguments( [ '--', 'hero', 'Hero' ] ) ).toEqual( {
      slug: 'hero',
      title: 'Hero',
      dynamic: false,
    } );
  } );

  it.each( [
    [ 'duplicate dynamic flag', [ 'hero', 'Hero', '--dynamic', '--dynamic' ] ],
    [ 'unknown flag', [ 'hero', 'Hero', '--unknown' ] ],
    [ 'missing title', [ 'hero' ] ],
    [ 'extra positional argument', [ 'hero', 'Hero', 'extra' ] ],
  ] )( 'rejects %s', ( _name, args ) => {
    expect( parseArguments( args ) ).toBeNull();
  } );

  it( 'returns exit code 2 and writes usage for invalid CLI input', async () => {
    const errors: string[] = [];
    let created = false;

    await expect(
      runCli( [ '--', 'hero' ], {
        createBlock: async () => {
          created = true;
        },
        writeError: ( message ) => errors.push( message ),
      } )
    ).resolves.toBe( 2 );

    expect( errors ).toEqual( [
      'Usage: pnpm block:create -- <kebab-slug> "<Title>" [--dynamic]\n',
    ] );
    expect( created ).toBe( false );
  } );

  it( 'returns exit code 1 without surfacing a generator stack trace', async () => {
    const errors: string[] = [];

    await expect(
      runCli( [ 'hero', 'Hero' ], {
        createBlock: async () => {
          throw new Error( 'registry unavailable' );
        },
        writeError: ( message ) => errors.push( message ),
      } )
    ).resolves.toBe( 1 );

    expect( errors ).toEqual( [
      'Unable to create block: registry unavailable\n',
    ] );
  } );

  it( 'returns exit code 0 and writes the created block on success', async () => {
    const output: string[] = [];
    const inputs: unknown[] = [];

    await expect(
      runCli( [ 'hero', 'Hero' ], {
        root: '/plugin',
        createBlock: async ( input ) => {
          inputs.push( input );
        },
        writeSuccess: ( message ) => output.push( message ),
      } )
    ).resolves.toBe( 0 );

    expect( inputs ).toEqual( [
      {
        root: '/plugin',
        slug: 'hero',
        title: 'Hero',
        dynamic: false,
      },
    ] );
    expect( output ).toEqual( [ 'Created celina/hero\n' ] );
  } );

  it( 'sets process exit status 2 and writes only usage for an invalid spawned command', async () => {
    const cwd = await cliProject();

    await expect( spawnCli( cwd, [ 'missing-title' ] ) ).resolves.toEqual( {
      status: 2,
      stderr:
        'Usage: pnpm block:create -- <kebab-slug> "<Title>" [--dynamic]\n',
      stdout: '',
    } );
  } );

  it( 'sets process exit status 1 and omits a stack for a spawned collision', async () => {
    const cwd = await cliProject( '["taken"]\n' );

    await expect( spawnCli( cwd, [ 'taken', 'Taken' ] ) ).resolves.toEqual( {
      status: 1,
      stderr: 'Unable to create block: block taken already exists\n',
      stdout: '',
    } );
  } );

  it( 'sets process exit status 0 and writes only success for a spawned generation', async () => {
    const cwd = await cliProject();

    await expect( spawnCli( cwd, [ 'spawned', 'Spawned' ] ) ).resolves.toEqual(
      {
        status: 0,
        stderr: '',
        stdout: 'Created celina/spawned\n',
      }
    );
    await expect(
      readFile(
        join(
          cwd,
          'wordpress/plugins/celina-blocks/src/blocks/spawned/block.json'
        ),
        'utf8'
      )
    ).resolves.toContain( 'celina/spawned' );
  } );
} );
