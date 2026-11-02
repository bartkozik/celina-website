import { spawnSync } from 'node:child_process';
import { createConnection } from 'node:net';
import { posix, win32 } from 'node:path';
import { pathToFileURL } from 'node:url';
import { parseContentArguments, type ContentGroup } from './cli.ts';
import { isolatedComposeProjectName } from '../wp/compose-project.ts';
import type { Spawn, SpawnResult } from '../wp/e2e.ts';

type Writer = ( message: string ) => void;
type Exit = ( code: number ) => void;
type PortProbe = ( port: number, host: string ) => Promise< boolean >;

export type ContentE2ERuntime = {
  cwd: string;
  env: NodeJS.ProcessEnv;
  execPath: string;
  platform: NodeJS.Platform;
};

export type ContentE2EArguments = {
  group?: ContentGroup;
};

export class ContentE2ECommandError extends Error {
  public constructor(
    message: string,
    public readonly exitCode: number
  ) {
    super( message );
    this.name = 'ContentE2ECommandError';
  }
}

type PnpmCommand = {
  args: string[];
  command: string;
};

function pnpmCommand(
  args: string[],
  runtime: ContentE2ERuntime
): PnpmCommand {
  const pnpmEntrypoint = runtime.env.npm_execpath;

  if ( runtime.platform === 'win32' && ! pnpmEntrypoint ) {
    throw new ContentE2ECommandError(
      'Cannot run pnpm on Windows without npm_execpath. Run this command through pnpm.',
      1
    );
  }

  return pnpmEntrypoint
    ? { args: [ pnpmEntrypoint, ...args ], command: runtime.execPath }
    : { args, command: 'pnpm' };
}

function isolatedEnvironment(
  runtime: ContentE2ERuntime,
  group?: ContentGroup
): NodeJS.ProcessEnv {
  const path = runtime.platform === 'win32' ? win32 : posix;
  const port = runtime.env.CELINA_CONTENT_E2E_PORT ?? '8894';
  const testsPort = runtime.env.CELINA_CONTENT_E2E_TESTS_PORT ?? '8895';

  return {
    ...runtime.env,
    ...( group ? { CELINA_CONTENT_GROUP: group } : {} ),
    COMPOSE_PROJECT_NAME: isolatedComposeProjectName(
      runtime.cwd,
      runtime.platform,
      'content-e2e'
    ),
    WP_ENV_HOME: path.join(
      runtime.cwd,
      'node_modules',
      '.cache',
      'celina-wp-env-content-e2e'
    ),
    WP_ENV_PORT: port,
    WP_ENV_TESTS_PORT: testsPort,
  };
}

function isExpectedMissingCompose(
  result: SpawnResult,
  environment: NodeJS.ProcessEnv,
  platform: NodeJS.Platform
): boolean {
  if ( result.error || result.signal || result.status !== 1 ) {
    return false;
  }

  const wpEnvHome = environment.WP_ENV_HOME;
  if ( ! wpEnvHome ) {
    return false;
  }

  const normalize = ( value: string ) => {
    const normalized = value.replaceAll( '\\', '/' );

    return platform === 'win32' ? normalized.toLowerCase() : normalized;
  };
  const output = normalize(
    `${ result.stderr ?? '' }\n${ result.stdout ?? '' }`
  );
  const ownHome = normalize( wpEnvHome ).replace( /\/+$/, '' );

  return (
    output.includes( `${ ownHome }/` ) &&
    output.includes( '/docker-compose.yml' ) &&
    /no such file or directory|system cannot find (?:the )?(?:file|path) specified/i.test(
      output
    )
  );
}

function runCommand(
  spawn: Spawn,
  runtime: ContentE2ERuntime,
  environment: NodeJS.ProcessEnv,
  args: string[],
  capture = false
): void {
  const resolved = pnpmCommand( args, runtime );
  const display = `${ resolved.command } ${ resolved.args.join( ' ' ) }`;
  const result = spawn( resolved.command, resolved.args, {
    cwd: runtime.cwd,
    ...( capture ? { encoding: 'utf8' as const } : {} ),
    env: environment,
    stdio: capture ? 'pipe' : 'inherit',
  } );

  if (
    capture &&
    isExpectedMissingCompose( result, environment, runtime.platform )
  ) {
    return;
  }

  const detail = [ result.stderr, result.stdout ]
    .filter( ( output ): output is string => Boolean( output?.trim() ) )
    .join( '\n' )
    .trim();
  const withDetail = ( message: string ) =>
    detail ? `${ message }\n${ detail }` : message;

  if ( result.error ) {
    throw new ContentE2ECommandError(
      withDetail( `Unable to run ${ display }: ${ result.error.message }` ),
      1
    );
  }
  if ( result.signal ) {
    throw new ContentE2ECommandError(
      withDetail(
        `Command terminated by signal ${ result.signal }: ${ display }`
      ),
      1
    );
  }
  if ( result.status === null ) {
    throw new ContentE2ECommandError(
      withDetail( `Command did not return an exit status: ${ display }` ),
      1
    );
  }
  if ( result.status !== 0 ) {
    throw new ContentE2ECommandError(
      withDetail(
        `Command failed with exit code ${ result.status }: ${ display }`
      ),
      result.status
    );
  }
}

export function probePort(
  port: number,
  host = '127.0.0.1'
): Promise< boolean > {
  return new Promise( ( resolve ) => {
    const socket = createConnection( { host, port } );
    const finish = ( open: boolean ) => {
      socket.destroy();
      resolve( open );
    };

    socket.once( 'connect', () => finish( true ) );
    socket.once( 'error', () => finish( false ) );
    socket.setTimeout( 500, () => finish( false ) );
  } );
}

export function parseContentE2EArguments(
  rawArguments: string[]
): ContentE2EArguments {
  const parsed = parseContentArguments( [ 'plan', ...rawArguments ] );

  return parsed.group ? { group: parsed.group } : {};
}

export async function runContentE2E(
  options: ContentE2EArguments,
  spawn: Spawn = spawnSync as Spawn,
  runtime: ContentE2ERuntime = {
    cwd: process.cwd(),
    env: process.env,
    execPath: process.execPath,
    platform: process.platform,
  },
  isPortOpen: PortProbe = probePort
): Promise< void > {
  const environment = isolatedEnvironment( runtime, options.group );
  const portHost = environment.CELINA_WP_ENV_PORT_HOST ?? '127.0.0.1';
  const portValues = [ environment.WP_ENV_PORT, environment.WP_ENV_TESTS_PORT ];
  const ports = portValues.map( ( port ) => Number( port ) );
  if (
    portValues.some(
      ( port ) =>
        ! /^(?:[1-9][0-9]{0,4})$/.test( port ?? '' ) || Number( port ) > 65_535
    ) ||
    new Set( ports ).size !== ports.length
  ) {
    throw new ContentE2ECommandError(
      'CELINA_CONTENT_E2E_PORT and CELINA_CONTENT_E2E_TESTS_PORT must be valid, distinct TCP ports.',
      1
    );
  }
  const stop = () =>
    runCommand(
      spawn,
      runtime,
      environment,
      [ 'exec', 'wp-env', 'stop' ],
      true
    );

  stop();
  for ( const port of ports ) {
    if ( await isPortOpen( port, portHost ) ) {
      throw new ContentE2ECommandError(
        `Port ${ port } is already in use after stopping the owned content E2E stack. Refusing to reuse an unrelated listener.`,
        1
      );
    }
  }
  runCommand(
    spawn,
    runtime,
    environment,
    [ 'exec', 'wp-env', 'clean', 'all' ],
    true
  );

  let commandFailure: ContentE2ECommandError | undefined;
  let cleanupFailure: ContentE2ECommandError | undefined;
  try {
    runCommand( spawn, runtime, environment, [ 'build' ] );
    runCommand( spawn, runtime, environment, [ 'wp:content:start' ] );
    runCommand( spawn, runtime, environment, [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'rewrite',
      'structure',
      '/%postname%/',
      '--hard',
    ] );
    runCommand( spawn, runtime, environment, [
      'content:plan',
      ...( options.group ? [ '--', '--group', options.group ] : [] ),
    ] );
    runCommand( spawn, runtime, environment, [
      'content:apply',
      ...( options.group ? [ '--', '--group', options.group ] : [] ),
    ] );
    runCommand( spawn, runtime, environment, [
      'content:plan',
      ...( options.group ? [ '--', '--group', options.group ] : [] ),
    ] );
    runCommand( spawn, runtime, environment, [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      '/var/www/html/tests/integration/canonical-patterns-contract.php',
    ] );
    runCommand( spawn, runtime, environment, [
      'exec',
      'playwright',
      'test',
      '--config=playwright.content.config.ts',
    ] );
  } catch ( error ) {
    commandFailure =
      error instanceof ContentE2ECommandError
        ? error
        : new ContentE2ECommandError( String( error ), 1 );
  } finally {
    try {
      stop();
    } catch ( error ) {
      cleanupFailure =
        error instanceof ContentE2ECommandError
          ? error
          : new ContentE2ECommandError( String( error ), 1 );
    }
  }

  if ( commandFailure && cleanupFailure ) {
    throw new ContentE2ECommandError(
      `${ commandFailure.message }\nCleanup also failed: ${ cleanupFailure.message }`,
      commandFailure.exitCode
    );
  }
  if ( cleanupFailure ) {
    throw cleanupFailure;
  }
  if ( commandFailure ) {
    throw commandFailure;
  }
}

export async function runContentE2ECli(
  args: string[] = process.argv.slice( 2 ),
  run: typeof runContentE2E = runContentE2E,
  writeError: Writer = ( message ) => process.stderr.write( message ),
  exit: Exit = process.exit
): Promise< void > {
  try {
    await run( parseContentE2EArguments( args ) );
  } catch ( error ) {
    writeError(
      `${ error instanceof Error ? error.message : String( error ) }\n`
    );
    exit( error instanceof ContentE2ECommandError ? error.exitCode : 1 );
  }
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  await runContentE2ECli();
}
