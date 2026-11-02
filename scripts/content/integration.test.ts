import { describe, expect, it, vi } from 'vitest';
import { runManagedContentIntegration } from './integration.ts';

describe( 'runManagedContentIntegration', () => {
  it( 'runs the live contract inside the current wp-env stack', () => {
    const run = vi.fn();

    runManagedContentIntegration( run );

    expect( run.mock.calls ).toEqual(
      [
        'managed-content-contract.php',
        'redesigned-patterns-contract.php',
        'redesigned-content-contract.php',
        'redesigned-connection-contract.php',
        'media-transform-contract.php',
        'media-migration-contract.php',
        'block-shell-contract.php',
        'block-shell-cta-contract.php',
      ].map( ( file ) => [
        [
          'exec',
          'wp-env',
          'run',
          'cli',
          'wp',
          'eval-file',
          `/var/www/html/tests/integration/${ file }`,
        ],
      ] )
    );
  } );
} );
