import { spawnSync } from 'node:child_process';
import { readdir } from 'node:fs/promises';
import { relative, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const ignored = new Set( [ 'build', 'node_modules', 'vendor' ] );
const phpLintProgram =
  'foreach ( array_slice( $argv, 1 ) as $file ) { $process = proc_open( [ "php", "-l", $file ], [ 1 => STDOUT, 2 => STDERR ], $pipes ); if ( ! is_resource( $process ) ) { fwrite( STDERR, "Could not run PHP lint\\n" ); exit( 1 ); } $status = proc_close( $process ); if ( 0 !== $status ) { exit( $status ); } }';

export type PhpLintRunner = ( files: string[] ) => void;
export type Spawn = (
  command: string,
  args: string[],
  options: { stdio: 'inherit' }
) => {
  error?: Error & { code?: string };
  signal: NodeJS.Signals | null;
  status: number | null;
};

export function toContainerPath(
  cwd: string,
  file: string,
  platform: NodeJS.Platform = process.platform
): string {
  const normalizedCwd = cwd.replaceAll( '\\', '/' ).replace( /\/$/, '' );
  const normalizedFile = file.replaceAll( '\\', '/' );

  if (
    platform === 'win32' &&
    ! normalizedFile
      .toLowerCase()
      .startsWith( `${ normalizedCwd.toLowerCase() }/` )
  ) {
    throw new Error( `PHP file is outside the mounted worktree: ${ file }` );
  }

  const relativePath =
    platform === 'win32'
      ? normalizedFile.slice( normalizedCwd.length + 1 )
      : relative( cwd, file );
  const path = relativePath.replaceAll( '\\', '/' );

  if (
    ! path ||
    path.startsWith( '../' ) ||
    path.includes( '/../' ) ||
    path.startsWith( '/' ) ||
    /^[A-Za-z]:/.test( path )
  ) {
    throw new Error( `PHP file is outside the mounted worktree: ${ file }` );
  }

  return path;
}

function throwForFailedCommand(
  command: string,
  result: ReturnType< Spawn >
): void {
  if ( result.error ) {
    throw new Error( `Unable to run ${ command }: ${ result.error.message }` );
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
}

function runPhpWithFallback(
  spawn: Spawn,
  cwd: string,
  nativeArgs: string[],
  dockerArgs: string[],
  dockerLabel: string
): boolean {
  const nativeResult = spawn( 'php', nativeArgs, { stdio: 'inherit' } );

  if ( nativeResult.error?.code !== 'ENOENT' ) {
    throwForFailedCommand( `php ${ nativeArgs.join( ' ' ) }`, nativeResult );
    return false;
  }

  const dockerResult = spawn(
    'docker',
    [
      'run',
      '--rm',
      '-v',
      `${ cwd }:/work:ro`,
      '-w',
      '/work',
      'php:8.2-cli',
      ...dockerArgs,
    ],
    { stdio: 'inherit' }
  );

  throwForFailedCommand( dockerLabel, dockerResult );
  return true;
}

export function createPhpLintRunner(
  spawn: Spawn = spawnSync,
  cwd = process.cwd()
): PhpLintRunner {
  return ( files ) => {
    const firstFile = files[ 0 ];
    if ( ! firstFile ) {
      return;
    }

    const usedDocker = runPhpWithFallback(
      spawn,
      cwd,
      [ '-l', firstFile ],
      [
        'php',
        '-r',
        phpLintProgram,
        '--',
        ...files.map( ( file ) => toContainerPath( cwd, file ) ),
      ],
      'docker PHP lint'
    );

    if ( usedDocker ) {
      return;
    }

    for ( const file of files.slice( 1 ) ) {
      const result = spawn( 'php', [ '-l', file ], { stdio: 'inherit' } );
      throwForFailedCommand( `php -l ${ file }`, result );
    }
  };
}

export function createPhpFixtureRunner(
  spawn: Spawn = spawnSync,
  cwd = process.cwd()
): ( file: string ) => void {
  return ( file ) => {
    const containerPath = toContainerPath( cwd, file );
    runPhpWithFallback(
      spawn,
      cwd,
      [ file ],
      [ 'php', containerPath ],
      'docker PHP fixture contract'
    );
  };
}

export async function findPhpFiles( root: string ): Promise< string[] > {
  const files: string[] = [];
  const rootPath = resolve( root );

  async function visit( directory: string ): Promise< void > {
    for ( const entry of await readdir( directory, {
      withFileTypes: true,
    } ) ) {
      if ( ignored.has( entry.name ) ) {
        continue;
      }

      const path = resolve( directory, entry.name );
      if ( entry.isDirectory() ) {
        await visit( path );
      }
      if ( entry.isFile() && entry.name.endsWith( '.php' ) ) {
        files.push( path );
      }
    }
  }

  try {
    await visit( rootPath );
  } catch ( error ) {
    const filesystemError = error as NodeJS.ErrnoException;
    if (
      filesystemError.code === 'ENOENT' &&
      filesystemError.path === rootPath
    ) {
      return [];
    }
    throw error;
  }

  return files.sort();
}

export async function main(
  roots = [ 'wordpress', 'fixtures', 'tests' ],
  runPhpLint = createPhpLintRunner()
): Promise< void > {
  const files: string[] = [];

  for ( const root of roots ) {
    files.push( ...( await findPhpFiles( root ) ) );
  }

  runPhpLint( files );
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ?? '' ).href ) {
  try {
    const roots = process.argv.slice( 2 );
    await main( roots.length > 0 ? roots : undefined );
  } catch ( error ) {
    process.stderr.write(
      `${ error instanceof Error ? error.message : String( error ) }\n`
    );
    process.exitCode = 1;
  }
}
