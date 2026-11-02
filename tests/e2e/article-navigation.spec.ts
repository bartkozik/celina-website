import { spawnSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

test( 'article navigation follows publication date and ID in both directions', () => {
  const result = spawnSync(
    process.platform === 'win32' ? 'pnpm.cmd' : 'pnpm',
    [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      '/var/www/html/tests/integration/article-navigation-contract.php',
    ],
    { cwd: process.cwd(), encoding: 'utf8', env: process.env }
  );
  const output = `${ result.stdout ?? '' }\n${ result.stderr ?? '' }`;

  expect( result.error ).toBeUndefined();
  expect( result.signal ).toBeNull();
  expect( result.status, output ).toBe( 0 );
  expect( output ).toContain( 'Article navigation contract passed' );
} );
