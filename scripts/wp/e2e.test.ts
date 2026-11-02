import { readFile } from 'node:fs/promises';
import { describe, expect, it, vi } from 'vitest';
import playwrightConfig from '../../playwright.config.ts';
import {
  E2ECommandError,
  runE2E,
  runE2ECli,
  type E2ERuntime,
  type Spawn,
  type SpawnResult,
} from './e2e.ts';

const success: SpawnResult = { signal: null, status: 0 };
const runtime: E2ERuntime = {
  cwd: 'C:\\workspace\\celina',
  env: {
    CI: 'true',
    COMPOSE_PROJECT_NAME: 'ambient-project',
    npm_execpath: 'C:\\tools\\pnpm.cjs',
  },
  execPath: 'C:\\Program Files\\nodejs\\node.exe',
  platform: 'win32',
};

describe( 'runE2E', () => {
  it( 'uses one isolated checkout environment in preflight, Playwright, and cleanup order', () => {
    const spawn = vi.fn< Spawn >( () => success );

    runE2E( spawn, runtime );

    const env = {
      CI: 'true',
      COMPOSE_PROJECT_NAME: 'celina-75c15a20cac7-e2e',
      npm_execpath: 'C:\\tools\\pnpm.cjs',
      WP_ENV_HOME:
        'C:\\workspace\\celina\\node_modules\\.cache\\celina-wp-env-e2e',
      WP_ENV_PORT: '8892',
      WP_ENV_TESTS_PORT: '8893',
    };
    const stopOptions = {
      cwd: 'C:\\workspace\\celina',
      encoding: 'utf8' as const,
      env,
      stdio: 'pipe' as const,
    };
    const playwrightOptions = {
      cwd: 'C:\\workspace\\celina',
      env,
      stdio: 'inherit' as const,
    };

    expect( spawn.mock.calls ).toEqual( [
      [
        'C:\\Program Files\\nodejs\\node.exe',
        [ 'C:\\tools\\pnpm.cjs', 'exec', 'wp-env', 'stop' ],
        stopOptions,
      ],
      [
        'C:\\Program Files\\nodejs\\node.exe',
        [ 'C:\\tools\\pnpm.cjs', 'exec', 'wp-env', 'clean', 'all' ],
        stopOptions,
      ],
      [
        'C:\\Program Files\\nodejs\\node.exe',
        [ 'C:\\tools\\pnpm.cjs', 'exec', 'playwright', 'test' ],
        playwrightOptions,
      ],
      [
        'C:\\Program Files\\nodejs\\node.exe',
        [ 'C:\\tools\\pnpm.cjs', 'exec', 'wp-env', 'stop' ],
        stopOptions,
      ],
    ] );
    expect( stopOptions ).not.toHaveProperty( 'shell' );
    expect( playwrightOptions ).not.toHaveProperty( 'shell' );
  } );

  it( 'uses the pnpm executable directly outside Windows when npm_execpath is absent', () => {
    const spawn = vi.fn< Spawn >( () => success );

    runE2E( spawn, {
      cwd: '/workspace/celina',
      env: {},
      execPath: '/usr/local/bin/node',
      platform: 'darwin',
    } );

    expect( spawn.mock.calls[ 0 ]?.[ 0 ] ).toBe( 'pnpm' );
    expect( spawn.mock.calls[ 0 ]?.[ 1 ] ).toEqual( [
      'exec',
      'wp-env',
      'stop',
    ] );
  } );

  it( 'fails safely on Windows without a JavaScript pnpm entrypoint', () => {
    const spawn = vi.fn( () => success );

    expect( () =>
      runE2E( spawn, {
        ...runtime,
        env: {},
      } )
    ).toThrow( 'Cannot run pnpm on Windows without npm_execpath.' );
    expect( spawn ).not.toHaveBeenCalled();
  } );

  it( 'does not run Playwright when the preflight stop fails', () => {
    const spawn = vi.fn( () => ( { signal: null, status: 2 } ) );

    expect( () => runE2E( spawn, runtime ) ).toThrow(
      'Command failed with exit code 2'
    );
    expect( spawn ).toHaveBeenCalledTimes( 1 );
  } );

  it( 'accepts only the missing compose file for its own fresh E2E home', () => {
    const missingOwnCompose = {
      signal: null,
      status: 1,
      stderr:
        'open c:\\WORKSPACE\\CELINA\\node_modules\\.cache\\celina-wp-env-e2e\\fixture-hash\\docker-compose.yml: no such file or directory',
      stdout: '',
    };
    const spawn = vi
      .fn< Spawn >()
      .mockReturnValueOnce( missingOwnCompose )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success );

    expect( () => runE2E( spawn, runtime ) ).not.toThrow();
    expect( spawn ).toHaveBeenCalledTimes( 4 );

    const foreignSpawn = vi.fn< Spawn >( () => ( {
      ...missingOwnCompose,
      stderr:
        'open C:\\foreign\\fixture-hash\\docker-compose.yml: no such file or directory',
    } ) );

    expect( () => runE2E( foreignSpawn, runtime ) ).toThrow(
      'Command failed with exit code 1'
    );
    expect( foreignSpawn ).toHaveBeenCalledTimes( 1 );
  } );

  it( 'keeps missing-compose path matching case-sensitive on POSIX', () => {
    const spawn = vi.fn< Spawn >( () => ( {
      signal: null,
      status: 1,
      stderr:
        'open /workspace/celina/node_modules/.cache/celina-wp-env-e2e/hash/docker-compose.yml: no such file or directory',
    } ) );

    expect( () =>
      runE2E( spawn, {
        cwd: '/Workspace/CELINA',
        env: {},
        execPath: '/usr/local/bin/node',
        platform: 'darwin',
      } )
    ).toThrow( 'Command failed with exit code 1' );
    expect( spawn ).toHaveBeenCalledTimes( 1 );
  } );

  it.each( [
    { label: 'success', playwright: success },
    {
      label: 'failure',
      playwright: { signal: null, status: 7 } satisfies SpawnResult,
    },
  ] )(
    'clears readiness in preflight and finally after $label',
    ( scenario ) => {
      const events: string[] = [];
      const spawn = vi.fn< Spawn >( ( _command, args ) => {
        const isPlaywright = args.includes( 'playwright' );
        let event = 'stop';
        if ( isPlaywright ) {
          event = 'playwright';
        } else if ( args.includes( 'clean' ) ) {
          event = 'clean-db';
        }
        events.push( event );
        return isPlaywright ? scenario.playwright : success;
      } );
      const clearReadiness = vi.fn( () => events.push( 'clear' ) );

      try {
        runE2E( spawn, runtime, clearReadiness );
      } catch ( error ) {
        if ( scenario.label !== 'failure' ) {
          throw error;
        }
      }

      expect( events ).toEqual( [
        'clear',
        'stop',
        'clean-db',
        'playwright',
        'clear',
        'stop',
      ] );
      expect( clearReadiness ).toHaveBeenCalledTimes( 2 );
    }
  );

  it.each( [
    {
      exitCode: 1,
      label: 'spawn errors',
      outcome: {
        error: new Error( 'executable not found' ),
        signal: null,
        status: null,
      },
      text: 'Unable to run',
    },
    {
      exitCode: 1,
      label: 'signals',
      outcome: { signal: 'SIGTERM', status: null },
      text: 'Command terminated by signal SIGTERM',
    },
    {
      exitCode: 1,
      label: 'missing exit statuses',
      outcome: { signal: null, status: null },
      text: 'Command did not return an exit status',
    },
    {
      exitCode: 7,
      label: 'nonzero exit statuses',
      outcome: { signal: null, status: 7 },
      text: 'Command failed with exit code 7',
    },
  ] satisfies Array< {
    exitCode: number;
    label: string;
    outcome: SpawnResult;
    text: string;
  } > )(
    'cleans up after Playwright $label and preserves the failure',
    ( { exitCode, outcome, text } ) => {
      const spawn = vi
        .fn< Spawn >()
        .mockReturnValueOnce( success )
        .mockReturnValueOnce( success )
        .mockReturnValueOnce( outcome )
        .mockReturnValueOnce( success );

      let caught: unknown;
      try {
        runE2E( spawn, runtime );
      } catch ( error ) {
        caught = error;
      }

      expect( caught ).toBeInstanceOf( E2ECommandError );
      expect( ( caught as E2ECommandError ).exitCode ).toBe( exitCode );
      expect( ( caught as Error ).message ).toContain( text );
      expect( spawn ).toHaveBeenCalledTimes( 4 );
      expect( spawn.mock.calls[ 3 ]?.[ 1 ] ).toEqual( [
        'C:\\tools\\pnpm.cjs',
        'exec',
        'wp-env',
        'stop',
      ] );
    }
  );

  it( 'reports cleanup failure after a successful Playwright run', () => {
    const spawn = vi
      .fn< Spawn >()
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( { signal: null, status: 9 } );

    let caught: unknown;
    try {
      runE2E( spawn, runtime );
    } catch ( error ) {
      caught = error;
    }

    expect( caught ).toBeInstanceOf( E2ECommandError );
    expect( ( caught as E2ECommandError ).exitCode ).toBe( 9 );
    expect( ( caught as Error ).message ).toContain(
      'Command failed with exit code 9'
    );
  } );

  it( 'preserves the Playwright exit code when cleanup also fails', () => {
    const spawn = vi
      .fn< Spawn >()
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( success )
      .mockReturnValueOnce( { signal: null, status: 7 } )
      .mockReturnValueOnce( { signal: null, status: 9 } );

    let caught: unknown;
    try {
      runE2E( spawn, runtime );
    } catch ( error ) {
      caught = error;
    }

    expect( caught ).toBeInstanceOf( E2ECommandError );
    expect( ( caught as E2ECommandError ).exitCode ).toBe( 7 );
    expect( ( caught as Error ).message ).toContain( 'Cleanup also failed' );
    expect( ( caught as Error ).message ).toContain( 'exit code 9' );
  } );
} );

describe( 'runE2ECli', () => {
  it( 'writes the command error and propagates its nonzero exit code', () => {
    const writeError = vi.fn();
    const exit = vi.fn();

    runE2ECli(
      () => {
        throw new E2ECommandError( 'Playwright failed', 7 );
      },
      writeError,
      exit
    );

    expect( writeError ).toHaveBeenCalledWith( 'Playwright failed\n' );
    expect( exit ).toHaveBeenCalledWith( 7 );
  } );
} );

describe( 'E2E configuration contract', () => {
  it( 'uses the isolated listener and never reuses an existing server', () => {
    const config = playwrightConfig as {
      use: { baseURL: string };
      webServer: {
        command: string;
        reuseExistingServer: boolean;
        timeout: number;
        url: string;
      };
    };

    expect( config.use.baseURL ).toBe( 'http://localhost:8892' );
    expect( config.webServer ).toMatchObject( {
      command: 'pnpm wp:start',
      reuseExistingServer: false,
      timeout: 600_000,
      url: 'http://localhost:8892/fixtures/e2e-ready.php',
    } );
  } );

  it( 'routes the package command through the lifecycle owner', async () => {
    const packageJson = JSON.parse(
      await readFile( new URL( '../../package.json', import.meta.url ), 'utf8' )
    ) as { scripts: Record< string, string > };

    expect( packageJson.scripts[ 'test:e2e' ] ).toBe( 'tsx scripts/wp/e2e.ts' );
  } );

  it( 'keeps the ignored readiness marker beside its local-only endpoint', async () => {
    const [ gitignore, endpoint ] = await Promise.all( [
      readFile( new URL( '../../.gitignore', import.meta.url ), 'utf8' ),
      readFile(
        new URL( '../../fixtures/e2e-ready.php', import.meta.url ),
        'utf8'
      ),
    ] );

    expect( gitignore.split( /\r?\n/ ) ).toContain(
      'fixtures/.celina-e2e-ready'
    );
    expect( gitignore ).not.toContain(
      'wordpress/themes/celina-child/celina-e2e-ready.txt'
    );
    expect( endpoint ).toContain( "__DIR__ . '/.celina-e2e-ready'" );
    expect( endpoint ).not.toContain( '/wp-content/themes/' );
    expect( endpoint ).not.toContain( '/wp-content/plugins/' );
  } );
} );
