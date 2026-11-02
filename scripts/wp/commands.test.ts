import { describe, expect, it, vi } from 'vitest';
import { createPnpmRunner } from './commands.ts';

describe( 'createPnpmRunner', () => {
  it( 'uses Node and npm_execpath on Windows with inherited stdio', () => {
    const spawn = vi.fn( () => ( { signal: null, status: 0 } ) );

    createPnpmRunner( spawn, {
      env: { npm_execpath: 'C:\\pnpm\\pnpm.cjs' },
      execPath: 'C:\\Program Files\\nodejs\\node.exe',
      platform: 'win32',
    } )( [ 'exec', 'wp-env', 'start' ] );

    expect( spawn ).toHaveBeenCalledWith(
      'C:\\Program Files\\nodejs\\node.exe',
      [ 'C:\\pnpm\\pnpm.cjs', 'exec', 'wp-env', 'start' ],
      { stdio: 'inherit' }
    );
  } );

  it( 'fails early on Windows without a JavaScript pnpm entrypoint', () => {
    const spawn = vi.fn();
    const run = createPnpmRunner( spawn, {
      env: {},
      execPath: 'C:\\Program Files\\nodejs\\node.exe',
      platform: 'win32',
    } );

    expect( () => run( [ 'lint' ] ) ).toThrow(
      'Cannot run pnpm on Windows without npm_execpath.'
    );
    expect( spawn ).not.toHaveBeenCalled();
  } );

  it( 'uses pnpm outside Windows', () => {
    const spawn = vi.fn( () => ( { signal: null, status: 0 } ) );

    createPnpmRunner( spawn, {
      env: {},
      execPath: '/usr/local/bin/node',
      platform: 'darwin',
    } )( [ 'typecheck' ] );

    expect( spawn ).toHaveBeenCalledWith( 'pnpm', [ 'typecheck' ], {
      stdio: 'inherit',
    } );
  } );

  it( 'reports a spawn error', () => {
    const run = createPnpmRunner(
      () => ( {
        error: new Error( 'executable not found' ),
        signal: null,
        status: null,
      } ),
      { env: {}, execPath: '/usr/bin/node', platform: 'linux' }
    );

    expect( () => run( [ 'lint' ] ) ).toThrow(
      'Unable to run pnpm lint: executable not found'
    );
  } );

  it( 'reports signal termination', () => {
    const run = createPnpmRunner(
      () => ( { signal: 'SIGTERM', status: null } ),
      { env: {}, execPath: '/usr/bin/node', platform: 'linux' }
    );

    expect( () => run( [ 'lint' ] ) ).toThrow(
      'Command terminated by signal SIGTERM: pnpm lint'
    );
  } );

  it( 'reports a missing exit status', () => {
    const run = createPnpmRunner( () => ( { signal: null, status: null } ), {
      env: {},
      execPath: '/usr/bin/node',
      platform: 'linux',
    } );

    expect( () => run( [ 'lint' ] ) ).toThrow(
      'Command did not return an exit status: pnpm lint'
    );
  } );

  it( 'reports a nonzero exit status', () => {
    const run = createPnpmRunner( () => ( { signal: null, status: 2 } ), {
      env: {},
      execPath: '/usr/bin/node',
      platform: 'linux',
    } );

    expect( () => run( [ 'lint' ] ) ).toThrow(
      'Command failed with exit code 2: pnpm lint'
    );
  } );
} );
