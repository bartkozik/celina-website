import { pathToFileURL } from 'node:url';
import { runPnpm, type Runner } from '../wp/commands.ts';

export function runManagedContentIntegration( run: Runner = runPnpm ): void {
  for ( const file of [
    'managed-content-contract.php',
    'redesigned-patterns-contract.php',
    'redesigned-content-contract.php',
    'redesigned-connection-contract.php',
    'media-transform-contract.php',
    'media-migration-contract.php',
    'block-shell-contract.php',
    'block-shell-cta-contract.php',
  ] ) {
    run( [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      `/var/www/html/tests/integration/${ file }`,
    ] );
  }
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  runManagedContentIntegration();
}
