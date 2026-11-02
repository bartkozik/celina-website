import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createPhpFixtureRunner } from './check-php.ts';

export function main(): void {
  const run = createPhpFixtureRunner();

  run( resolve( 'tests/php/fixtures-bootstrap.contract.php' ) );
  run( resolve( 'tests/php/e2e-ready.contract.php' ) );
  run( resolve( 'tests/php/managed-content.contract.php' ) );
  run( resolve( 'tests/php/pattern-source.contract.php' ) );
  run( resolve( 'tests/php/client-content-assets.contract.php' ) );
  run( resolve( 'tests/php/footer-source.contract.php' ) );
  run( resolve( 'tests/php/hubspot-form-presets.contract.php' ) );
  run( resolve( 'tests/php/style-source.contract.php' ) );
  run( resolve( 'tests/php/theme-assets.contract.php' ) );
  run( resolve( 'tests/php/article-source.contract.php' ) );
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  try {
    main();
  } catch ( error ) {
    process.stderr.write(
      `${ error instanceof Error ? error.message : String( error ) }\n`
    );
    process.exitCode = 1;
  }
}
