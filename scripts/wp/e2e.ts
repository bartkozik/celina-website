import { spawnSync } from 'node:child_process';
import { posix, win32 } from 'node:path';
import { pathToFileURL } from 'node:url';
import { isolatedComposeProjectName } from './compose-project.ts';
import { clearE2EReadiness } from './e2e-readiness.ts';

type Writer = ( message: string ) => void;
type Exit = ( code: number ) => void;

export type SpawnResult = {
  error?: Error;
  signal: NodeJS.Signals | null;
  status: number | null;
  stderr?: string | null;
  stdout?: string | null;
};

export type Spawn = (
  command: string,
  args: string[],
  options: {
    cwd: string;
    encoding?: 'utf8';
    env: NodeJS.ProcessEnv;
    stdio: 'inherit' | 'pipe';
  }
) => SpawnResult;

export type E2ERuntime = {
  cwd: string;
  env: NodeJS.ProcessEnv;
  execPath: string;
  platform: NodeJS.Platform;
};

export class E2ECommandError extends Error {
  public constructor(
    message: string,
    public readonly exitCode: number
  ) {
    super( message );
    this.name = 'E2ECommandError';
  }
}

type PnpmCommand = {
  args: string[];
  command: string;
};

function pnpmCommand( args: string[], runtime: E2ERuntime ): PnpmCommand {
  const pnpmEntrypoint = runtime.env.npm_execpath;

  if ( runtime.platform === 'win32' && ! pnpmEntrypoint ) {
    throw new E2ECommandError(
      'Cannot run pnpm on Windows without npm_execpath. Run this command through pnpm.',
      1
    );
  }

  return pnpmEntrypoint
    ? {
        args: [ pnpmEntrypoint, ...args ],
        command: runtime.execPath,
      }
    : { args, command: 'pnpm' };
}

function isolatedEnvironment( runtime: E2ERuntime ): NodeJS.ProcessEnv {
  const path = runtime.platform === 'win32' ? win32 : posix;

  return {
    ...runtime.env,
    COMPOSE_PROJECT_NAME: isolatedComposeProjectName(
      runtime.cwd,
      runtime.platform,
      'e2e'
    ),
    WP_ENV_HOME: path.join(
      runtime.cwd,
      'node_modules',
      '.cache',
      'celina-wp-env-e2e'
    ),
    WP_ENV_PORT: '8892',
    WP_ENV_TESTS_PORT: '8893',
  };
}

function runCommand(
  spawn: Spawn,
  runtime: E2ERuntime,
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
    throw new E2ECommandError(
      withDetail( `Unable to run ${ display }: ${ result.error.message }` ),
      1
    );
  }
  if ( result.signal ) {
    throw new E2ECommandError(
      withDetail(
        `Command terminated by signal ${ result.signal }: ${ display }`
      ),
      1
    );
  }
  if ( result.status === null ) {
    throw new E2ECommandError(
      withDetail( `Command did not return an exit status: ${ display }` ),
      1
    );
  }
  if ( result.status !== 0 ) {
    throw new E2ECommandError(
      withDetail(
        `Command failed with exit code ${ result.status }: ${ display }`
      ),
      result.status
    );
  }
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
  const missingFile =
    /no such file or directory|system cannot find (?:the )?(?:file|path) specified/i;

  return (
    output.includes( `${ ownHome }/` ) &&
    output.includes( '/docker-compose.yml' ) &&
    missingFile.test( output )
  );
}

export function runE2E(
  spawn: Spawn = spawnSync as Spawn,
  runtime: E2ERuntime = {
    cwd: process.cwd(),
    env: process.env,
    execPath: process.execPath,
    platform: process.platform,
  },
  clearReadiness: () => void = clearE2EReadiness
): void {
  const environment = isolatedEnvironment( runtime );
  const stop = () =>
    runCommand(
      spawn,
      runtime,
      environment,
      [ 'exec', 'wp-env', 'stop' ],
      true
    );

  const clearAndStop = () => {
    let readinessFailure: E2ECommandError | undefined;
    let stopFailure: E2ECommandError | undefined;

    try {
      clearReadiness();
    } catch ( error ) {
      readinessFailure = new E2ECommandError(
        `Unable to clear E2E readiness marker: ${
          error instanceof Error ? error.message : String( error )
        }`,
        1
      );
    }

    try {
      stop();
    } catch ( error ) {
      stopFailure =
        error instanceof E2ECommandError
          ? error
          : new E2ECommandError( String( error ), 1 );
    }

    if ( readinessFailure && stopFailure ) {
      throw new E2ECommandError(
        `${ readinessFailure.message }\nStack stop also failed: ${ stopFailure.message }`,
        readinessFailure.exitCode
      );
    }
    if ( readinessFailure ) {
      throw readinessFailure;
    }
    if ( stopFailure ) {
      throw stopFailure;
    }
  };

  clearAndStop();

  let operationFailure: E2ECommandError | undefined;
  let cleanupFailure: E2ECommandError | undefined;
  try {
    runCommand(
      spawn,
      runtime,
      environment,
      [ 'exec', 'wp-env', 'clean', 'all' ],
      true
    );
    runCommand( spawn, runtime, environment, [ 'exec', 'playwright', 'test' ] );
  } catch ( error ) {
    operationFailure =
      error instanceof E2ECommandError
        ? error
        : new E2ECommandError( String( error ), 1 );
  } finally {
    try {
      clearAndStop();
    } catch ( error ) {
      cleanupFailure =
        error instanceof E2ECommandError
          ? error
          : new E2ECommandError( String( error ), 1 );
    }
  }

  if ( operationFailure && cleanupFailure ) {
    throw new E2ECommandError(
      `${ operationFailure.message }\nCleanup also failed: ${ cleanupFailure.message }`,
      operationFailure.exitCode
    );
  }
  if ( cleanupFailure ) {
    throw cleanupFailure;
  }
  if ( operationFailure ) {
    throw operationFailure;
  }
}

export function runE2ECli(
  run: () => void = runE2E,
  writeError: Writer = ( message ) => process.stderr.write( message ),
  exit: Exit = process.exit
): void {
  try {
    run();
  } catch ( error ) {
    writeError(
      `${ error instanceof Error ? error.message : String( error ) }\n`
    );
    exit( error instanceof E2ECommandError ? error.exitCode : 1 );
  }
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  runE2ECli();
}
