import { pathToFileURL } from 'node:url';
import { startWordPress, type StartWordPressOptions } from './commands.ts';

type Writer = ( message: string ) => void;
type Exit = ( code: number ) => void;
type Starter = ( options: StartWordPressOptions ) => void;

export function startManagedContentCli(
  start: Starter = startWordPress,
  writeSuccess: Writer = ( message ) => process.stdout.write( message ),
  writeError: Writer = ( message ) => process.stderr.write( message ),
  exit: Exit = process.exit,
  environment: NodeJS.ProcessEnv = process.env
): void {
  try {
    start( { seedFixtures: false } );
    const port = environment.WP_ENV_PORT ?? '8888';
    writeSuccess(
      `CELINA managed content: http://localhost:${ port } (admin/password)\n`
    );
  } catch ( error ) {
    writeError(
      `${ error instanceof Error ? error.message : String( error ) }\n`
    );
    exit( 1 );
  }
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  startManagedContentCli();
}
