import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { generateBlock } from './generator.ts';

const usage = 'Usage: pnpm block:create -- <kebab-slug> "<Title>" [--dynamic]';

export interface BlockArguments {
  slug: string;
  title: string;
  dynamic: boolean;
}

export interface CliOperations {
  createBlock?: typeof generateBlock;
  root?: string;
  writeError?: ( message: string ) => void;
  writeSuccess?: ( message: string ) => void;
}

export function parseArguments( args: string[] ): BlockArguments | null {
  const forwarded = args[ 0 ] === '--' ? args.slice( 1 ) : args;
  const dynamicFlags = forwarded.filter( ( value ) => value === '--dynamic' );
  if (
    dynamicFlags.length > 1 ||
    forwarded.some(
      ( value ) => value.startsWith( '--' ) && value !== '--dynamic'
    )
  ) {
    return null;
  }
  const positional = forwarded.filter( ( value ) => value !== '--dynamic' );
  const [ slug, title ] = positional;

  if ( ! slug || ! title || positional.length !== 2 ) {
    return null;
  }

  return { slug, title, dynamic: dynamicFlags.length === 1 };
}

export async function runCli(
  args: string[],
  operations: CliOperations = {}
): Promise< number > {
  const input = parseArguments( args );
  if ( ! input ) {
    ( operations.writeError ?? process.stderr.write.bind( process.stderr ) )(
      `${ usage }\n`
    );
    return 2;
  }

  try {
    await ( operations.createBlock ?? generateBlock )( {
      root: operations.root ?? resolve( 'wordpress/plugins/celina-blocks' ),
      ...input,
    } );
  } catch ( error ) {
    const message = error instanceof Error ? error.message : 'unknown error';
    ( operations.writeError ?? process.stderr.write.bind( process.stderr ) )(
      `Unable to create block: ${ message }\n`
    );
    return 1;
  }
  ( operations.writeSuccess ?? process.stdout.write.bind( process.stdout ) )(
    `Created celina/${ input.slug }\n`
  );
  return 0;
}

if (
  process.argv[ 1 ] &&
  resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url )
) {
  const exitCode = await runCli( process.argv.slice( 2 ) );
  process.exitCode = exitCode;
}
