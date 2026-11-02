import { spawnSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

test( 'fixture media keeps one URL and bounded files when its DB record is reset', () => {
  const result = spawnSync(
    process.platform === 'win32' ? 'pnpm.cmd' : 'pnpm',
    [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      '/var/www/html/tests/integration/fixture-media-runtime-contract.php',
    ],
    {
      cwd: process.cwd(),
      encoding: 'utf8',
      env: process.env,
    }
  );
  const output = `${ result.stdout ?? '' }\n${ result.stderr ?? '' }`;

  expect( result.error ).toBeUndefined();
  expect( result.signal ).toBeNull();
  expect( result.status, output ).toBe( 0 );
  expect( output ).toContain( 'Fixture media runtime contract passed.' );
} );
