import { spawnSync } from 'node:child_process';

export type Runner = ( args: string[] ) => void;
export type Spawn = (
  command: string,
  args: string[],
  options: { stdio: 'inherit' }
) => {
  error?: Error;
  signal: NodeJS.Signals | null;
  status: number | null;
};
export type PnpmRuntime = {
  env: NodeJS.ProcessEnv;
  execPath: string;
  platform: NodeJS.Platform;
};

export function createPnpmRunner(
  spawn: Spawn = spawnSync,
  runtime: PnpmRuntime = {
    env: process.env,
    execPath: process.execPath,
    platform: process.platform,
  }
): Runner {
  return ( args ) => {
    const pnpmEntrypoint = runtime.env.npm_execpath;
    const executable = pnpmEntrypoint ? runtime.execPath : 'pnpm';
    const commandArgs = pnpmEntrypoint ? [ pnpmEntrypoint, ...args ] : args;

    if ( runtime.platform === 'win32' && ! pnpmEntrypoint ) {
      throw new Error(
        'Cannot run pnpm on Windows without npm_execpath. Run this command through pnpm.'
      );
    }

    const command = `${ executable } ${ commandArgs.join( ' ' ) }`;
    const result = spawn( executable, commandArgs, { stdio: 'inherit' } );

    if ( result.error ) {
      throw new Error(
        `Unable to run ${ command }: ${ result.error.message }`
      );
    }
    if ( result.signal ) {
      throw new Error(
        `Command terminated by signal ${ result.signal }: ${ command }`
      );
    }
    if ( result.status === null ) {
      throw new Error( `Command did not return an exit status: ${ command }` );
    }
    if ( result.status !== 0 ) {
      throw new Error(
        `Command failed with exit code ${ result.status }: ${ command }`
      );
    }
  };
}

export const runPnpm: Runner = createPnpmRunner();

export type StartWordPressOptions = {
  run?: Runner;
  seedFixtures?: boolean;
};

export function startWordPress( options: StartWordPressOptions = {} ): void {
  const { run = runPnpm, seedFixtures = true } = options;
  const commands = [
    [ 'exec', 'wp-env', 'start' ],
    [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'theme',
      'install',
      'https://downloads.wordpress.org/theme/generatepress.3.6.1.zip',
      '--force',
    ],
    [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'theme',
      'activate',
      'celina-child',
    ],
    [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'plugin',
      'activate',
      'celina-blocks',
    ],
    [ 'exec', 'wp-env', 'run', 'cli', 'wp', 'rewrite', 'flush', '--hard' ],
  ];

  if ( seedFixtures ) {
    commands.splice( commands.length - 1, 0, [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      '/var/www/html/fixtures/bootstrap.php',
    ] );
  }

  for ( const command of commands ) {
    run( command );
  }
}
