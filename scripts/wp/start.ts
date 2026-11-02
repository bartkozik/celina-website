import { pathToFileURL } from 'node:url';
import { startWordPress } from './commands.ts';
import { clearE2EReadiness, markE2EReadiness } from './e2e-readiness.ts';

type Writer = ( message: string ) => void;
type Exit = ( code: number ) => void;
type E2EControl = {
  clearReadiness: () => void;
  keepAlive: () => void;
  markReadiness: () => void;
};

function keepE2EWebServerAlive(): void {
  setInterval( () => {}, 2_147_483_647 );
}

const defaultE2EControl: E2EControl = {
  clearReadiness: clearE2EReadiness,
  keepAlive: keepE2EWebServerAlive,
  markReadiness: markE2EReadiness,
};

export function startWordPressCli(
  start: () => void = startWordPress,
  writeSuccess: Writer = ( message ) => process.stdout.write( message ),
  writeError: Writer = ( message ) => process.stderr.write( message ),
  exit: Exit = process.exit,
  environment: NodeJS.ProcessEnv = process.env,
  e2eControl: E2EControl = defaultE2EControl
): void {
  try {
    const e2eMode =
      environment.WP_ENV_PORT === '8892' &&
      environment.WP_ENV_TESTS_PORT === '8893';

    if ( e2eMode ) {
      e2eControl.clearReadiness();
    }

    start();
    const port = environment.WP_ENV_PORT ?? '8888';
    writeSuccess(
      `CELINA WordPress: http://localhost:${ port } (admin/password)\n`
    );

    if ( e2eMode ) {
      e2eControl.markReadiness();
      e2eControl.keepAlive();
    }
  } catch ( error ) {
    writeError(
      `${ error instanceof Error ? error.message : String( error ) }\n`
    );
    exit( 1 );
  }
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  startWordPressCli();
}
