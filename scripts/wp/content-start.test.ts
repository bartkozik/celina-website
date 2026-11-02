import { describe, expect, it, vi } from 'vitest';
import { startManagedContentCli } from './content-start.ts';

describe( 'startManagedContentCli', () => {
  it( 'starts the non-fixture profile and prints its local URL', () => {
    const start = vi.fn();
    const write = vi.fn();

    startManagedContentCli( start, write, vi.fn(), vi.fn(), {
      WP_ENV_PORT: '8894',
    } );

    expect( start ).toHaveBeenCalledWith( { seedFixtures: false } );
    expect( write ).toHaveBeenCalledWith(
      'CELINA managed content: http://localhost:8894 (admin/password)\n'
    );
  } );

  it( 'reports startup failures without attempting cleanup', () => {
    const error = vi.fn();
    const exit = vi.fn();
    const start = vi.fn( () => {
      throw new Error( 'Docker unavailable' );
    } );

    startManagedContentCli( start, vi.fn(), error, exit );

    expect( error ).toHaveBeenCalledWith( 'Docker unavailable\n' );
    expect( exit ).toHaveBeenCalledWith( 1 );
  } );
} );
