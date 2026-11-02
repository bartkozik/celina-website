import { readFile } from 'node:fs/promises';
import { describe, expect, it } from 'vitest';

type WpEnvConfig = {
  config: Record< string, boolean >;
  core: null;
  mappings: Record< string, string >;
  phpVersion: string;
  plugins: string[];
  port: number;
  testsPort: number;
  themes: string[];
};

describe( '.wp-env.json', () => {
  it( 'defines the consumed development and test environment contract', async () => {
    const config = JSON.parse(
      await readFile( new URL( '../../.wp-env.json', import.meta.url ), 'utf8' )
    ) as WpEnvConfig;

    expect( config ).toMatchObject( {
      core: null,
      phpVersion: '8.2',
      port: 8888,
      testsPort: 8891,
      plugins: [ './wordpress/plugins/celina-blocks' ],
      themes: [ './wordpress/themes/celina-child' ],
      mappings: {
        'celina-workbench': './workbench',
        fixtures: './fixtures',
        'tests/integration': './tests/integration',
      },
      config: {
        WP_DEBUG: true,
        WP_DEBUG_LOG: true,
        SCRIPT_DEBUG: true,
      },
    } );
  } );
} );
