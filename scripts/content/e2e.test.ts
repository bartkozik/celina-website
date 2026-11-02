import { readFile } from 'node:fs/promises';
import { describe, expect, it, vi } from 'vitest';
import playwrightContentConfig from '../../playwright.content.config.ts';
import {
  ContentE2ECommandError,
  parseContentE2EArguments,
  runContentE2E,
  type ContentE2ERuntime,
} from './e2e.ts';
import type { Spawn, SpawnResult } from '../wp/e2e.ts';

const success: SpawnResult = { signal: null, status: 0 };
const runtime: ContentE2ERuntime = {
  cwd: 'C:\\workspace\\celina',
  env: {
    CI: 'true',
    COMPOSE_PROJECT_NAME: 'ambient-project',
    npm_execpath: 'C:\\tools\\pnpm.cjs',
  },
  execPath: 'C:\\Program Files\\nodejs\\node.exe',
  platform: 'win32',
};

describe( 'parseContentE2EArguments', () => {
  it( 'accepts the same optional group selector as content apply', () => {
    expect( parseContentE2EArguments( [] ) ).toEqual( {} );
    expect(
      parseContentE2EArguments( [ '--', '--group', 'brand-pages' ] )
    ).toEqual( { group: 'brand-pages' } );
    expect( parseContentE2EArguments( [ '--group=offer-pages' ] ) ).toEqual( {
      group: 'offer-pages',
    } );
  } );

  it( 'rejects unknown groups before starting Docker', () => {
    expect( () =>
      parseContentE2EArguments( [ '--group', 'unknown' ] )
    ).toThrow( 'Unknown content group.' );
  } );
} );

describe( 'runContentE2E', () => {
  it( 'owns an isolated stack and runs import before browser acceptance', async () => {
    const spawn = vi.fn< Spawn >( () => success );
    const isPortOpen = vi.fn( async () => false );

    await runContentE2E( { group: 'brand-pages' }, spawn, runtime, isPortOpen );

    const env = {
      CI: 'true',
      CELINA_CONTENT_GROUP: 'brand-pages',
      COMPOSE_PROJECT_NAME: 'celina-75c15a20cac7-content-e2e',
      npm_execpath: 'C:\\tools\\pnpm.cjs',
      WP_ENV_HOME:
        'C:\\workspace\\celina\\node_modules\\.cache\\celina-wp-env-content-e2e',
      WP_ENV_PORT: '8894',
      WP_ENV_TESTS_PORT: '8895',
    };
    const inherited = { cwd: runtime.cwd, env };

    expect( isPortOpen.mock.calls ).toEqual( [
      [ 8894, '127.0.0.1' ],
      [ 8895, '127.0.0.1' ],
    ] );
    expect( spawn.mock.calls ).toEqual( [
      [
        runtime.execPath,
        [ runtime.env.npm_execpath, 'exec', 'wp-env', 'stop' ],
        { ...inherited, encoding: 'utf8', stdio: 'pipe' },
      ],
      [
        runtime.execPath,
        [ runtime.env.npm_execpath, 'exec', 'wp-env', 'clean', 'all' ],
        { ...inherited, encoding: 'utf8', stdio: 'pipe' },
      ],
      [
        runtime.execPath,
        [ runtime.env.npm_execpath, 'build' ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [ runtime.env.npm_execpath, 'wp:content:start' ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [
          runtime.env.npm_execpath,
          'exec',
          'wp-env',
          'run',
          'cli',
          'wp',
          'rewrite',
          'structure',
          '/%postname%/',
          '--hard',
        ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [
          runtime.env.npm_execpath,
          'content:plan',
          '--',
          '--group',
          'brand-pages',
        ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [
          runtime.env.npm_execpath,
          'content:apply',
          '--',
          '--group',
          'brand-pages',
        ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [
          runtime.env.npm_execpath,
          'content:plan',
          '--',
          '--group',
          'brand-pages',
        ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [
          runtime.env.npm_execpath,
          'exec',
          'wp-env',
          'run',
          'cli',
          'wp',
          'eval-file',
          '/var/www/html/tests/integration/canonical-patterns-contract.php',
        ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [
          runtime.env.npm_execpath,
          'exec',
          'playwright',
          'test',
          '--config=playwright.content.config.ts',
        ],
        { ...inherited, stdio: 'inherit' },
      ],
      [
        runtime.execPath,
        [ runtime.env.npm_execpath, 'exec', 'wp-env', 'stop' ],
        { ...inherited, encoding: 'utf8', stdio: 'pipe' },
      ],
    ] );
  } );

  it( 'refuses to reuse an unrelated listener after stopping its own stack', async () => {
    const spawn = vi.fn< Spawn >( () => success );

    await expect(
      runContentE2E( {}, spawn, runtime, async ( port ) => port === 8894 )
    ).rejects.toThrow( 'Port 8894 is already in use' );
    expect( spawn ).toHaveBeenCalledTimes( 1 );
  } );

  it( 'honors dedicated port overrides without inheriting ambient wp-env ports', async () => {
    const spawn = vi.fn< Spawn >( () => success );
    const isPortOpen = vi.fn( async () => false );

    await runContentE2E(
      {},
      spawn,
      {
        ...runtime,
        env: {
          ...runtime.env,
          CELINA_CONTENT_E2E_PORT: '18994',
          CELINA_CONTENT_E2E_TESTS_PORT: '18995',
          WP_ENV_PORT: '28888',
          WP_ENV_TESTS_PORT: '28889',
        },
      },
      isPortOpen
    );

    expect( isPortOpen.mock.calls ).toEqual( [
      [ 18994, '127.0.0.1' ],
      [ 18995, '127.0.0.1' ],
    ] );
    expect( spawn.mock.calls[ 0 ]?.[ 2 ]?.env ).toMatchObject( {
      WP_ENV_PORT: '18994',
      WP_ENV_TESTS_PORT: '18995',
    } );
  } );

  it( 'rejects invalid or duplicate dedicated ports before touching Docker', async () => {
    for ( const [ port, testsPort ] of [
      [ 'invalid', '18995' ],
      [ '0', '18995' ],
      [ ' 18994 ', '18995' ],
      [ '1e4', '18995' ],
      [ '18994.0', '18995' ],
      [ '018994', '18995' ],
      [ '65536', '18995' ],
      [ '18994', '18994' ],
    ] ) {
      const spawn = vi.fn< Spawn >( () => success );

      await expect(
        runContentE2E(
          {},
          spawn,
          {
            ...runtime,
            env: {
              ...runtime.env,
              CELINA_CONTENT_E2E_PORT: port,
              CELINA_CONTENT_E2E_TESTS_PORT: testsPort,
            },
          },
          async () => false
        )
      ).rejects.toThrow( /valid, distinct TCP ports/ );
      expect( spawn ).not.toHaveBeenCalled();
    }
  } );

  it( 'probes the remote Docker service instead of its local CI forwarder', async () => {
    const spawn = vi.fn< Spawn >( () => success );
    const isPortOpen = vi.fn( async () => false );

    await runContentE2E(
      {},
      spawn,
      {
        ...runtime,
        env: {
          ...runtime.env,
          CELINA_WP_ENV_PORT_HOST: 'docker',
        },
      },
      isPortOpen
    );

    expect( isPortOpen.mock.calls ).toEqual( [
      [ 8894, 'docker' ],
      [ 8895, 'docker' ],
    ] );
  } );

  it( 'stops the owned stack when import or Playwright fails', async () => {
    const spawn = vi
      .fn< Spawn >()
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( { signal: null, status: 7 } )
      .mockReturnValueOnce( success );

    await expect(
      runContentE2E(
        { group: 'brand-pages' },
        spawn,
        runtime,
        async () => false
      )
    ).rejects.toMatchObject( {
      exitCode: 7,
    } satisfies Partial< ContentE2ECommandError > );
    expect( spawn.mock.calls.at( -1 )?.[ 1 ] ).toEqual( [
      runtime.env.npm_execpath,
      'exec',
      'wp-env',
      'stop',
    ] );
  } );
} );

describe( 'content E2E configuration', () => {
  it( 'uses only the lifecycle-owned content listener', () => {
    const config = playwrightContentConfig as {
      use: { baseURL: string };
      webServer?: unknown;
      workers: number;
    };

    expect( config.use.baseURL ).toBe( 'http://localhost:8894' );
    expect( config.workers ).toBe( 1 );
    expect( config.webServer ).toBeUndefined();
  } );

  it( 'exposes one package command for the complete lifecycle', async () => {
    const packageJson = JSON.parse(
      await readFile( new URL( '../../package.json', import.meta.url ), 'utf8' )
    ) as { scripts: Record< string, string > };

    expect( packageJson.scripts[ 'test:e2e:content' ] ).toBe(
      'tsx scripts/content/e2e.ts'
    );
  } );
} );
