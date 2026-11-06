import {
  lstat,
  mkdir,
  readFile,
  realpath,
  rename,
  rmdir,
  rm,
  writeFile,
} from 'node:fs/promises';
import { basename, isAbsolute, join, relative } from 'node:path';

const journalFilename = '.celina-block-create.transaction.json';
const journalTemporaryFilename = `${ journalFilename }.tmp`;
const reservedSlugs = new Set( [ 'core', 'wordpress', 'wp', 'build', 'src' ] );
const safeSlug = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;
const uuid =
  '[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

export interface TransactionOperations {
  remove?: typeof rm;
  rename?: typeof rename;
}

export interface BlockTransactionJournal {
  version: 1;
  slug: string;
  temporaryDirectory: string;
  temporaryRegistry: string;
  previousRegistry: string;
  nextRegistry: string;
  removeSourceIfEmpty: boolean;
  removeBlocksIfEmpty: boolean;
}

export interface BlockTransactionPaths {
  blocks: string;
  destination: string;
  journal: string;
  journalTemporary: string;
  registry: string;
  registryTemporary: string;
  source: string;
  temporaryDirectory: string;
}

export class BlockTransactionCleanupError extends AggregateError {
  readonly leftoverPaths: string[];

  constructor( message: string, errors: unknown[], leftoverPaths: string[] ) {
    const uniquePaths = [ ...new Set( leftoverPaths ) ].sort();
    const suffix =
      uniquePaths.length === 0
        ? ''
        : `; recovery artifacts remain at: ${ uniquePaths.join( ', ' ) }`;
    super( errors, `${ message }${ suffix }` );
    this.name = 'BlockTransactionCleanupError';
    this.leftoverPaths = uniquePaths;
  }
}

function isErrno( error: unknown, code: string ): boolean {
  return ( error as NodeJS.ErrnoException ).code === code;
}

function isInside( path: string, root: string ): boolean {
  const fromRoot = relative( root, path );
  return ! fromRoot.startsWith( '..' ) && ! isAbsolute( fromRoot );
}

function assertInside( path: string, root: string ): void {
  if ( ! isInside( path, root ) ) {
    throw new Error(
      `unsafe block generator path outside plugin root: ${ path }`
    );
  }
}

async function pathExists( path: string ): Promise< boolean > {
  try {
    await lstat( path );
    return true;
  } catch ( error ) {
    if ( isErrno( error, 'ENOENT' ) ) {
      return false;
    }
    throw error;
  }
}

async function canonicalPathIfExists(
  root: string,
  path: string
): Promise< string | null > {
  assertInside( path, root );
  try {
    const canonical = await realpath( path );
    if ( canonical !== path || ! isInside( canonical, root ) ) {
      throw new Error( `block generator path is not root-local: ${ path }` );
    }
    return canonical;
  } catch ( error ) {
    if ( isErrno( error, 'ENOENT' ) ) {
      return null;
    }
    throw error;
  }
}

async function readCanonicalFile(
  root: string,
  path: string
): Promise< string | null > {
  return ( await canonicalPathIfExists( root, path ) ) === null
    ? null
    : readFile( path, 'utf8' );
}

function parseRegistry( content: string ): string[] | null {
  try {
    const parsed = JSON.parse( content ) as unknown;
    return Array.isArray( parsed ) &&
      parsed.every( ( slug ) => typeof slug === 'string' )
      ? parsed
      : null;
  } catch {
    return null;
  }
}

function expectedNextRegistry(
  previousRegistry: string,
  slug: string
): string | null {
  const previous = parseRegistry( previousRegistry );
  if ( previous === null || previous.includes( slug ) ) {
    return null;
  }
  return `${ JSON.stringify( [ ...previous, slug ].sort(), null, 2 ) }\n`;
}

function isJournal( value: unknown ): value is BlockTransactionJournal {
  if ( typeof value !== 'object' || value === null ) {
    return false;
  }
  const candidate = value as Record< string, unknown >;
  const keys = Object.keys( candidate ).sort();
  const expectedKeys = [
    'nextRegistry',
    'previousRegistry',
    'removeBlocksIfEmpty',
    'removeSourceIfEmpty',
    'slug',
    'temporaryDirectory',
    'temporaryRegistry',
    'version',
  ].sort();
  if ( JSON.stringify( keys ) !== JSON.stringify( expectedKeys ) ) {
    return false;
  }
  if (
    candidate.version !== 1 ||
    typeof candidate.slug !== 'string' ||
    ! safeSlug.test( candidate.slug ) ||
    reservedSlugs.has( candidate.slug ) ||
    typeof candidate.temporaryDirectory !== 'string' ||
    typeof candidate.temporaryRegistry !== 'string' ||
    typeof candidate.previousRegistry !== 'string' ||
    typeof candidate.nextRegistry !== 'string' ||
    typeof candidate.removeSourceIfEmpty !== 'boolean' ||
    typeof candidate.removeBlocksIfEmpty !== 'boolean' ||
    ( candidate.removeSourceIfEmpty && ! candidate.removeBlocksIfEmpty ) ||
    basename( candidate.temporaryDirectory ) !== candidate.temporaryDirectory ||
    basename( candidate.temporaryRegistry ) !== candidate.temporaryRegistry
  ) {
    return false;
  }
  const directoryPattern = new RegExp(
    `^\\.${ candidate.slug }\\.${ uuid }\\.stage$`
  );
  const registryPattern = new RegExp(
    `^\\.blocks\\.json\\.${ uuid }\\.stage$`
  );
  return (
    directoryPattern.test( candidate.temporaryDirectory ) &&
    registryPattern.test( candidate.temporaryRegistry ) &&
    expectedNextRegistry( candidate.previousRegistry, candidate.slug ) ===
      candidate.nextRegistry
  );
}

export function transactionPaths(
  root: string,
  journal: BlockTransactionJournal
): BlockTransactionPaths {
  const source = join( root, 'src' );
  const blocks = join( source, 'blocks' );
  const paths = {
    blocks,
    destination: join( blocks, journal.slug ),
    journal: join( root, journalFilename ),
    journalTemporary: join( root, journalTemporaryFilename ),
    registry: join( root, 'blocks.json' ),
    registryTemporary: join( root, journal.temporaryRegistry ),
    source,
    temporaryDirectory: join( blocks, journal.temporaryDirectory ),
  };
  Object.values( paths ).forEach( ( path ) => assertInside( path, root ) );
  return paths;
}

function flattenErrors( error: unknown ): unknown[] {
  const errors: unknown[] =
    error instanceof AggregateError ? error.errors : [ error ];
  return [ ...errors ];
}

function errorLeftovers( error: unknown ): string[] {
  if (
    error instanceof AggregateError &&
    'leftoverPaths' in error &&
    Array.isArray( error.leftoverPaths )
  ) {
    return error.leftoverPaths.filter(
      ( path ): path is string => typeof path === 'string'
    );
  }
  return [];
}

function combineErrors(
  message: string,
  primary: unknown,
  cleanup: unknown
): BlockTransactionCleanupError {
  return new BlockTransactionCleanupError(
    message,
    [ ...flattenErrors( primary ), ...flattenErrors( cleanup ) ],
    [ ...errorLeftovers( primary ), ...errorLeftovers( cleanup ) ]
  );
}

async function writeJournal(
  root: string,
  journal: BlockTransactionJournal,
  operations: TransactionOperations
): Promise< void > {
  const paths = transactionPaths( root, journal );
  try {
    await writeFile(
      paths.journalTemporary,
      `${ JSON.stringify( journal, null, 2 ) }\n`,
      { flag: 'wx' }
    );
    await ( operations.rename ?? rename )(
      paths.journalTemporary,
      paths.journal
    );
  } catch ( error ) {
    try {
      await ( operations.remove ?? rm )( paths.journalTemporary, {
        force: true,
      } );
    } catch ( cleanupError ) {
      const leftovers = ( await pathExists( paths.journalTemporary ) )
        ? [ paths.journalTemporary ]
        : [];
      throw new BlockTransactionCleanupError(
        'journal publication and cleanup both failed',
        [ error, cleanupError ],
        leftovers
      );
    }
    throw error;
  }
}

async function readJournal(
  root: string
): Promise< BlockTransactionJournal | null > {
  const path = join( root, journalFilename );
  const content = await readCanonicalFile( root, path );
  if ( content === null ) {
    return null;
  }
  let value: unknown;
  try {
    value = JSON.parse( content );
  } catch {
    throw new Error( 'invalid block generator transaction journal' );
  }
  if ( ! isJournal( value ) ) {
    throw new Error( 'invalid block generator transaction journal' );
  }
  return value;
}

async function collectLeftover(
  path: string,
  errors: unknown[],
  leftovers: string[],
  error: unknown
): Promise< void > {
  errors.push( error );
  try {
    if ( await pathExists( path ) ) {
      leftovers.push( path );
    }
  } catch ( inspectionError ) {
    errors.push( inspectionError );
    leftovers.push( path );
  }
}

async function removeAndCollect(
  root: string,
  path: string,
  recursive: boolean,
  operations: TransactionOperations,
  errors: unknown[],
  leftovers: string[]
): Promise< void > {
  try {
    if ( ( await canonicalPathIfExists( root, path ) ) === null ) {
      return;
    }
    await ( operations.remove ?? rm )( path, {
      force: true,
      recursive,
    } );
  } catch ( error ) {
    await collectLeftover( path, errors, leftovers, error );
  }
}

async function removeParentIfEmptyAndCollect(
  root: string,
  path: string,
  enabled: boolean,
  errors: unknown[],
  leftovers: string[]
): Promise< void > {
  if ( ! enabled ) {
    return;
  }
  try {
    if ( ( await canonicalPathIfExists( root, path ) ) === null ) {
      return;
    }
    await rmdir( path );
  } catch ( error ) {
    if ( isErrno( error, 'ENOENT' ) || isErrno( error, 'ENOTEMPTY' ) ) {
      return;
    }
    await collectLeftover( path, errors, leftovers, error );
  }
}

async function assertRollbackRegistry(
  root: string,
  paths: BlockTransactionPaths,
  journal: BlockTransactionJournal
): Promise< void > {
  const current = await readCanonicalFile( root, paths.registry );
  if ( current === journal.previousRegistry ) {
    return;
  }
  throw new BlockTransactionCleanupError(
    'refusing recovery from an unexpected blocks.json state',
    [
      new Error(
        current === null
          ? 'blocks.json is missing'
          : 'blocks.json does not match the journal previous state'
      ),
    ],
    [ paths.journal ]
  );
}

async function rollbackTransaction(
  root: string,
  journal: BlockTransactionJournal,
  operations: TransactionOperations
): Promise< void > {
  const paths = transactionPaths( root, journal );
  await assertRollbackRegistry( root, paths, journal );
  const errors: unknown[] = [];
  const leftovers: string[] = [];
  await removeAndCollect(
    root,
    paths.registryTemporary,
    false,
    operations,
    errors,
    leftovers
  );
  await removeAndCollect(
    root,
    paths.destination,
    true,
    operations,
    errors,
    leftovers
  );
  await removeAndCollect(
    root,
    paths.temporaryDirectory,
    true,
    operations,
    errors,
    leftovers
  );
  await removeParentIfEmptyAndCollect(
    root,
    paths.blocks,
    journal.removeBlocksIfEmpty,
    errors,
    leftovers
  );
  await removeParentIfEmptyAndCollect(
    root,
    paths.source,
    journal.removeSourceIfEmpty,
    errors,
    leftovers
  );
  await removeAndCollect(
    root,
    paths.journalTemporary,
    false,
    operations,
    errors,
    leftovers
  );

  if ( errors.length > 0 ) {
    throw new BlockTransactionCleanupError(
      'block transaction rollback failed',
      errors,
      [ ...leftovers, paths.journal ]
    );
  }

  await removeAndCollect(
    root,
    paths.journal,
    false,
    operations,
    errors,
    leftovers
  );
  if ( errors.length > 0 ) {
    throw new BlockTransactionCleanupError(
      'block transaction journal cleanup failed',
      errors,
      leftovers
    );
  }
}

async function finalizeTransaction(
  root: string,
  journal: BlockTransactionJournal,
  operations: TransactionOperations
): Promise< void > {
  const paths = transactionPaths( root, journal );
  const errors: unknown[] = [];
  const leftovers: string[] = [];
  await removeAndCollect(
    root,
    paths.registryTemporary,
    false,
    operations,
    errors,
    leftovers
  );
  await removeAndCollect(
    root,
    paths.temporaryDirectory,
    true,
    operations,
    errors,
    leftovers
  );
  await removeAndCollect(
    root,
    paths.journalTemporary,
    false,
    operations,
    errors,
    leftovers
  );
  if ( errors.length > 0 ) {
    throw new BlockTransactionCleanupError(
      'committed block transaction cleanup failed',
      errors,
      [ ...leftovers, paths.journal ]
    );
  }
  await removeAndCollect(
    root,
    paths.journal,
    false,
    operations,
    errors,
    leftovers
  );
  if ( errors.length > 0 ) {
    throw new BlockTransactionCleanupError(
      'committed block transaction journal cleanup failed',
      errors,
      leftovers
    );
  }
}

async function transactionIsCommitted(
  root: string,
  journal: BlockTransactionJournal
): Promise< boolean > {
  const paths = transactionPaths( root, journal );
  const destination = await canonicalPathIfExists( root, paths.destination );
  const registry = await readCanonicalFile( root, paths.registry );
  return destination === paths.destination && registry === journal.nextRegistry;
}

export async function recoverBlockTransaction(
  root: string,
  operations: TransactionOperations = {}
): Promise< void > {
  const journal = await readJournal( root );
  if ( journal === null ) {
    const journalTemporary = join( root, journalTemporaryFilename );
    if ( ( await canonicalPathIfExists( root, journalTemporary ) ) !== null ) {
      try {
        await ( operations.remove ?? rm )( journalTemporary, { force: true } );
      } catch ( error ) {
        const leftovers = ( await pathExists( journalTemporary ) )
          ? [ journalTemporary ]
          : [];
        throw new BlockTransactionCleanupError(
          'stale journal temporary cleanup failed',
          [ error ],
          leftovers
        );
      }
    }
    return;
  }
  if ( await transactionIsCommitted( root, journal ) ) {
    await finalizeTransaction( root, journal, operations );
    return;
  }
  await rollbackTransaction( root, journal, operations );
}

export async function commitBlockTransaction(
  root: string,
  journal: BlockTransactionJournal,
  operations: TransactionOperations,
  stage: (
    paths: BlockTransactionPaths,
    createTemporaryDirectory: () => Promise< void >
  ) => Promise< void >
): Promise< void > {
  if ( ! isJournal( journal ) ) {
    throw new Error( 'invalid block generator transaction journal' );
  }
  const paths = transactionPaths( root, journal );
  await writeJournal( root, journal, operations );
  const createTemporaryDirectory = async (): Promise< void > => {
    await mkdir( paths.temporaryDirectory );
  };

  let commitError: unknown;
  try {
    await stage( paths, createTemporaryDirectory );
    await ( operations.rename ?? rename )(
      paths.temporaryDirectory,
      paths.destination
    );
    await ( operations.rename ?? rename )(
      paths.registryTemporary,
      paths.registry
    );
  } catch ( error ) {
    commitError = error;
  }

  if ( commitError !== undefined ) {
    try {
      await rollbackTransaction( root, journal, operations );
    } catch ( cleanupError ) {
      throw combineErrors(
        'block generation and rollback both failed',
        commitError,
        cleanupError
      );
    }
    throw commitError;
  }
  await finalizeTransaction( root, journal, operations );
}
