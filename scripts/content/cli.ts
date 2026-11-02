import { pathToFileURL } from 'node:url';
import { runPnpm, type Runner } from '../wp/commands.ts';

const modes = [ 'plan', 'apply' ] as const;
const groups = [
  'brand-pages',
  'offer-pages',
  'people-contact-pages',
  'news-resources',
  'legal-pages',
] as const;

type ContentMode = ( typeof modes )[ number ];
export type ContentGroup = ( typeof groups )[ number ];

export type ContentArguments = {
  mode: ContentMode;
  group?: ContentGroup;
};

type Writer = ( message: string ) => void;
type Exit = ( code: number ) => void;

const usage =
  'Usage: pnpm content:plan|content:apply [-- --group <brand-pages|offer-pages|people-contact-pages|news-resources|legal-pages>]';

export class ContentCliUsageError extends Error {
  constructor( message: string ) {
    super( message );
    this.name = 'ContentCliUsageError';
  }
}

export function parseContentArguments(
  rawArguments: string[]
): ContentArguments {
  const args = rawArguments.filter( ( argument ) => argument !== '--' );
  const [ mode, ...options ] = args;

  if ( ! modes.includes( mode as ContentMode ) ) {
    throw new ContentCliUsageError( 'Mode must be plan or apply.' );
  }

  if ( options.length === 0 ) {
    return { mode: mode as ContentMode };
  }

  let group: string | undefined;
  const firstOption = options[ 0 ] ?? '';
  if ( options.length === 2 && firstOption === '--group' ) {
    group = options[ 1 ];
  } else if ( options.length === 1 && firstOption.startsWith( '--group=' ) ) {
    group = firstOption.slice( '--group='.length );
  } else {
    throw new ContentCliUsageError( 'Expected at most one --group value.' );
  }

  if ( ! groups.includes( group as ContentGroup ) ) {
    throw new ContentCliUsageError( 'Unknown content group.' );
  }

  return { group: group as ContentGroup, mode: mode as ContentMode };
}

export function buildContentCommand( args: ContentArguments ): string[] {
  return [
    'exec',
    'wp-env',
    'run',
    'cli',
    'wp',
    'eval-file',
    '/var/www/html/celina-workbench/content/command.php',
    args.mode,
    ...( args.group ? [ args.group ] : [] ),
  ];
}

export function runContentCli(
  args: string[] = process.argv.slice( 2 ),
  run: Runner = runPnpm,
  writeError: Writer = ( message ) => process.stderr.write( message ),
  exit: Exit = process.exit
): void {
  try {
    run( buildContentCommand( parseContentArguments( args ) ) );
  } catch ( error ) {
    const isUsageError = error instanceof ContentCliUsageError;
    const message = error instanceof Error ? error.message : String( error );
    writeError( `${ message }${ isUsageError ? `\n${ usage }` : '' }\n` );
    exit( isUsageError ? 2 : 1 );
  }
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  runContentCli();
}
