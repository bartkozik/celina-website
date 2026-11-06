import { execFile } from 'node:child_process';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { promisify } from 'node:util';
import { describe, expect, it, vi } from 'vitest';
import {
  createPhpLintRunner,
  findPhpFiles,
  toContainerPath,
} from './check-php.ts';

const execFileAsync = promisify( execFile );

describe( 'findPhpFiles', () => {
  it( 'returns sorted PHP files and ignores vendor/build directories', async () => {
    const root = await mkdtemp( join( tmpdir(), 'celina-php-' ) );

    try {
      const included = [
        join( root, 'alpha', 'first.php' ),
        join( root, 'middle.php' ),
        join( root, 'zeta', 'last.php' ),
      ];

      await mkdir( join( root, 'alpha' ) );
      await mkdir( join( root, 'zeta' ) );
      await mkdir( join( root, 'build' ) );
      await mkdir( join( root, 'node_modules' ) );
      await mkdir( join( root, 'vendor' ) );
      await writeFile( included[ 2 ]!, '<?php' );
      await writeFile( included[ 1 ]!, '<?php' );
      await writeFile( included[ 0 ]!, '<?php' );
      await writeFile( join( root, 'build', 'ignored.php' ), '<?php' );
      await writeFile( join( root, 'node_modules', 'ignored.php' ), '<?php' );
      await writeFile( join( root, 'vendor', 'ignored.php' ), '<?php' );
      await writeFile( join( root, 'ignored.txt' ), 'nope' );

      await expect( findPhpFiles( root ) ).resolves.toEqual( included );
    } finally {
      await rm( root, { recursive: true, force: true } );
    }
  } );

  it( 'returns an empty list for a missing requested root', async () => {
    const root = await mkdtemp( join( tmpdir(), 'celina-php-' ) );

    try {
      await expect( findPhpFiles( join( root, 'missing' ) ) ).resolves.toEqual(
        []
      );
    } finally {
      await rm( root, { recursive: true, force: true } );
    }
  } );

  it( 'runs the PHP lint CLI through the project toolchain', async () => {
    await expect(
      execFileAsync( 'pnpm', [ 'php:lint' ], { cwd: process.cwd() } )
    ).resolves.toMatchObject( {
      stdout: expect.stringContaining( 'fixtures/bootstrap.php' ) as unknown,
    } );
  }, 90_000 );
} );

describe( 'toContainerPath', () => {
  it( 'converts Windows paths with spaces and metacharacters to POSIX paths', () => {
    expect(
      toContainerPath(
        'C:\\CELINA Workbench',
        'C:\\CELINA Workbench\\tests\\php\\fixture;$(safe).php',
        'win32'
      )
    ).toBe( 'tests/php/fixture;$(safe).php' );
  } );

  it( 'rejects paths outside the mounted worktree', () => {
    expect( () =>
      toContainerPath(
        'C:\\CELINA Workbench',
        'D:\\outside\\fixture.php',
        'win32'
      )
    ).toThrow( 'PHP file is outside the mounted worktree' );
  } );
} );

describe( 'createPhpLintRunner', () => {
  const fixture = '/workspace/fixtures/bootstrap.php';
  const phpLintProgram =
    'foreach ( array_slice( $argv, 1 ) as $file ) { $process = proc_open( [ "php", "-l", $file ], [ 1 => STDOUT, 2 => STDERR ], $pipes ); if ( ! is_resource( $process ) ) { fwrite( STDERR, "Could not run PHP lint\\n" ); exit( 1 ); } $status = proc_close( $process ); if ( 0 !== $status ) { exit( $status ); } }';

  it( 'uses native PHP when it is available', () => {
    const spawn = vi.fn( () => ( { signal: null, status: 0 } ) );

    createPhpLintRunner( spawn, '/workspace' )( [ fixture ] );

    expect( spawn ).toHaveBeenCalledWith( 'php', [ '-l', fixture ], {
      stdio: 'inherit',
    } );
  } );

  it( 'uses the pinned PHP image only when native PHP is unavailable', () => {
    const notFound = Object.assign( new Error( 'not found' ), {
      code: 'ENOENT',
    } );
    const spawn = vi
      .fn()
      .mockReturnValueOnce( { error: notFound, signal: null, status: null } )
      .mockReturnValueOnce( { signal: null, status: 0 } );

    createPhpLintRunner( spawn, '/workspace' )( [ fixture ] );

    expect( spawn ).toHaveBeenNthCalledWith(
      2,
      'docker',
      [
        'run',
        '--rm',
        '-v',
        '/workspace:/work:ro',
        '-w',
        '/work',
        'php:8.2-cli',
        'php',
        '-r',
        phpLintProgram,
        '--',
        'fixtures/bootstrap.php',
      ],
      { stdio: 'inherit' }
    );
  } );

  it( 'batches fallback PHP lint into one container invocation', () => {
    const notFound = Object.assign( new Error( 'not found' ), {
      code: 'ENOENT',
    } );
    const spawn = vi
      .fn()
      .mockReturnValueOnce( { error: notFound, signal: null, status: null } )
      .mockReturnValueOnce( { signal: null, status: 0 } );

    createPhpLintRunner(
      spawn,
      '/workspace'
    )( [
      '/workspace/fixtures/bootstrap.php',
      '/workspace/tests/php/fixtures-bootstrap.contract.php',
    ] );

    expect( spawn ).toHaveBeenCalledTimes( 2 );
    expect( spawn ).toHaveBeenLastCalledWith(
      'docker',
      [
        'run',
        '--rm',
        '-v',
        '/workspace:/work:ro',
        '-w',
        '/work',
        'php:8.2-cli',
        'php',
        '-r',
        phpLintProgram,
        '--',
        'fixtures/bootstrap.php',
        'tests/php/fixtures-bootstrap.contract.php',
      ],
      { stdio: 'inherit' }
    );
  } );

  it( 'does not mask a native PHP syntax failure with Docker', () => {
    const spawn = vi.fn( () => ( { signal: null, status: 1 } ) );
    const run = createPhpLintRunner( spawn, '/workspace' );

    expect( () => run( [ fixture ] ) ).toThrow(
      'Command failed with exit code 1: php -l /workspace/fixtures/bootstrap.php'
    );
    expect( spawn ).toHaveBeenCalledTimes( 1 );
  } );

  it( 'surfaces a Docker failure after native PHP is unavailable', () => {
    const notFound = Object.assign( new Error( 'not found' ), {
      code: 'ENOENT',
    } );
    const spawn = vi
      .fn()
      .mockReturnValueOnce( { error: notFound, signal: null, status: null } )
      .mockReturnValueOnce( {
        error: new Error( 'Docker unavailable' ),
        signal: null,
        status: null,
      } );
    const run = createPhpLintRunner( spawn, '/workspace' );

    expect( () => run( [ fixture ] ) ).toThrow(
      'Unable to run docker PHP lint: Docker unavailable'
    );
  } );
} );
