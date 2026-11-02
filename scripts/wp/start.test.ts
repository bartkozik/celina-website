import { existsSync, unlinkSync } from 'node:fs';
import { dirname, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it, vi } from 'vitest';
import { startWordPress } from './commands.ts';
import { startWordPressCli } from './start.ts';

describe( 'startWordPress', () => {
  it( 'starts wp-env, activates the theme and plugin, and seeds fixtures', () => {
    const run = vi.fn();
    startWordPress( { run } );
    expect( run.mock.calls ).toEqual( [
      [ [ 'exec', 'wp-env', 'start' ] ],
      [
        [
          'exec',
          'wp-env',
          'run',
          'cli',
          'wp',
          'theme',
          'install',
          'https://downloads.wordpress.org/theme/generatepress.3.6.1.zip',
          '--force',
        ],
      ],
      [
        [
          'exec',
          'wp-env',
          'run',
          'cli',
          'wp',
          'theme',
          'activate',
          'celina-child',
        ],
      ],
      [
        [
          'exec',
          'wp-env',
          'run',
          'cli',
          'wp',
          'plugin',
          'activate',
          'celina-blocks',
        ],
      ],
      [
        [
          'exec',
          'wp-env',
          'run',
          'cli',
          'wp',
          'eval-file',
          '/var/www/html/fixtures/bootstrap.php',
        ],
      ],
      [
        [ 'exec', 'wp-env', 'run', 'cli', 'wp', 'rewrite', 'flush', '--hard' ],
      ],
    ] );
  } );

  it( 'stops after the first failed command', () => {
    const run = vi.fn( () => {
      throw new Error( 'Docker unavailable' );
    } );
    expect( () => startWordPress( { run } ) ).toThrow( 'Docker unavailable' );
    expect( run ).toHaveBeenCalledTimes( 1 );
  } );

  it( 'omits only synthetic fixture seeding for managed content mode', () => {
    const run = vi.fn();

    startWordPress( { run, seedFixtures: false } );

    expect( run.mock.calls ).not.toContainEqual( [
      [
        'exec',
        'wp-env',
        'run',
        'cli',
        'wp',
        'eval-file',
        '/var/www/html/fixtures/bootstrap.php',
      ],
    ] );
    expect( run.mock.calls.flat( 2 ) ).not.toContain( 'clean' );
    expect( run ).toHaveBeenCalledWith( [ 'exec', 'wp-env', 'start' ] );
    expect( run ).toHaveBeenCalledWith( [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'rewrite',
      'flush',
      '--hard',
    ] );
  } );
} );

describe( 'startWordPressCli', () => {
  it( 'writes the success message with a newline', () => {
    const writeSuccess = vi.fn();

    startWordPressCli( vi.fn(), writeSuccess, vi.fn(), vi.fn() );

    expect( writeSuccess ).toHaveBeenCalledWith(
      'CELINA WordPress: http://localhost:8888 (admin/password)\n'
    );
  } );

  it( 'reports the wp-env port override used by the current process', () => {
    const writeSuccess = vi.fn();

    startWordPressCli( vi.fn(), writeSuccess, vi.fn(), vi.fn(), {
      WP_ENV_PORT: '8892',
    } );

    expect( writeSuccess ).toHaveBeenCalledWith(
      'CELINA WordPress: http://localhost:8892 (admin/password)\n'
    );
  } );

  it( 'publishes E2E readiness only after bootstrap and keeps the process alive', () => {
    const events: string[] = [];

    startWordPressCli(
      () => events.push( 'start' ),
      vi.fn(),
      vi.fn(),
      vi.fn(),
      {
        WP_ENV_PORT: '8892',
        WP_ENV_TESTS_PORT: '8893',
      },
      {
        clearReadiness: () => events.push( 'clear' ),
        keepAlive: () => events.push( 'keep-alive' ),
        markReadiness: () => events.push( 'ready' ),
      }
    );

    expect( events ).toEqual( [ 'clear', 'start', 'ready', 'keep-alive' ] );
  } );

  it( 'stores E2E readiness under fixtures and outside package roots', () => {
    vi.useFakeTimers();
    const projectRoot = fileURLToPath( new URL( '../../', import.meta.url ) );
    const marker = resolve( projectRoot, 'fixtures', '.celina-e2e-ready' );
    const oldThemeMarker = resolve(
      projectRoot,
      'wordpress',
      'themes',
      'celina-child',
      'celina-e2e-ready.txt'
    );
    const packageRoots = [
      resolve( projectRoot, 'wordpress', 'themes', 'celina-child' ),
      resolve( projectRoot, 'wordpress', 'plugins', 'celina-blocks' ),
    ];

    for ( const path of [ marker, oldThemeMarker ] ) {
      try {
        unlinkSync( path );
      } catch ( error ) {
        if ( ( error as NodeJS.ErrnoException ).code !== 'ENOENT' ) {
          throw error;
        }
      }
    }

    try {
      startWordPressCli( vi.fn(), vi.fn(), vi.fn(), vi.fn(), {
        WP_ENV_PORT: '8892',
        WP_ENV_TESTS_PORT: '8893',
      } );

      expect( dirname( marker ) ).toBe( resolve( projectRoot, 'fixtures' ) );
      for ( const packageRoot of packageRoots ) {
        expect( marker.startsWith( `${ packageRoot }${ sep }` ) ).toBe( false );
      }
      expect( existsSync( marker ) ).toBe( true );
      expect( existsSync( oldThemeMarker ) ).toBe( false );
    } finally {
      for ( const path of [ marker, oldThemeMarker ] ) {
        try {
          unlinkSync( path );
        } catch ( error ) {
          if ( ( error as NodeJS.ErrnoException ).code !== 'ENOENT' ) {
            throw error;
          }
        }
      }
      vi.useRealTimers();
    }
  } );

  it( 'does not publish readiness or keep E2E alive after bootstrap failure', () => {
    const events: string[] = [];

    startWordPressCli(
      () => {
        events.push( 'start' );
        throw new Error( 'Bootstrap failed' );
      },
      vi.fn(),
      vi.fn(),
      vi.fn(),
      {
        WP_ENV_PORT: '8892',
        WP_ENV_TESTS_PORT: '8893',
      },
      {
        clearReadiness: () => events.push( 'clear' ),
        keepAlive: () => events.push( 'keep-alive' ),
        markReadiness: () => events.push( 'ready' ),
      }
    );

    expect( events ).toEqual( [ 'clear', 'start' ] );
  } );

  it( 'writes a thrown error message with a newline and exits', () => {
    const writeError = vi.fn();
    const exit = vi.fn();

    startWordPressCli(
      () => {
        throw new Error( 'Docker unavailable' );
      },
      vi.fn(),
      writeError,
      exit
    );

    expect( writeError ).toHaveBeenCalledWith( 'Docker unavailable\n' );
    expect( exit ).toHaveBeenCalledWith( 1 );
  } );
} );
