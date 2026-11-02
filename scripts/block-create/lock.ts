import { randomUUID } from 'node:crypto';
import {
  link,
  lstat,
  readFile,
  realpath,
  rename,
  rm,
  writeFile,
} from 'node:fs/promises';
import { hostname } from 'node:os';
import { isAbsolute, join, relative } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const lockName = '.celina-block-create.lock';
const timeoutMilliseconds = 5_000;
const retryMilliseconds = 5;
const uuid =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

export interface LockOperations {
  afterPublishedOwnerRead?: () => Promise< void > | void;
  onLockContention?: () => void;
  remove?: typeof rm;
}

interface LockOwner {
  version: 1;
  pid: number;
  hostname: string;
  token: string;
  createdAt: string;
}

interface LockRecord {
  content: string;
  device: number;
  inode: number;
  owner: LockOwner;
}

interface LockHandle {
  candidate: string;
  owner: LockOwner;
  path: string;
}

export class BlockLockCleanupError extends AggregateError {
  readonly leftoverPaths: string[];

  constructor( message: string, errors: unknown[], leftoverPaths: string[] ) {
    const uniquePaths = [ ...new Set( leftoverPaths ) ].sort();
    const suffix =
      uniquePaths.length === 0
        ? ''
        : `; lock artifacts remain at: ${ uniquePaths.join( ', ' ) }`;
    super( errors, `${ message }${ suffix }` );
    this.name = 'BlockLockCleanupError';
    this.leftoverPaths = uniquePaths;
  }
}

class InvalidLockRecordError extends Error {}

function isErrno( error: unknown, code: string ): boolean {
  return ( error as NodeJS.ErrnoException ).code === code;
}

function isInside( path: string, root: string ): boolean {
  const fromRoot = relative( root, path );
  return ! fromRoot.startsWith( '..' ) && ! isAbsolute( fromRoot );
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

async function remainingPaths( paths: string[] ): Promise< string[] > {
  const remaining: string[] = [];
  for ( const path of paths ) {
    try {
      if ( await pathExists( path ) ) {
        remaining.push( path );
      }
    } catch {
      remaining.push( path );
    }
  }
  return remaining;
}

function candidatePath( root: string, owner: LockOwner ): string {
  return join(
    root,
    `${ lockName }.${ owner.pid }.${ owner.token }.candidate`
  );
}

function ownerContent( owner: LockOwner ): string {
  return `${ JSON.stringify( owner, null, 2 ) }\n`;
}

function parseOwner( content: string ): LockOwner | null {
  let value: unknown;
  try {
    value = JSON.parse( content );
  } catch {
    return null;
  }
  if ( typeof value !== 'object' || value === null ) {
    return null;
  }
  const candidate = value as Record< string, unknown >;
  if (
    JSON.stringify( Object.keys( candidate ).sort() ) !==
      JSON.stringify(
        [ 'createdAt', 'hostname', 'pid', 'token', 'version' ].sort()
      ) ||
    candidate.version !== 1 ||
    typeof candidate.pid !== 'number' ||
    ! Number.isSafeInteger( candidate.pid ) ||
    candidate.pid <= 0 ||
    typeof candidate.hostname !== 'string' ||
    candidate.hostname.length === 0 ||
    typeof candidate.token !== 'string' ||
    ! uuid.test( candidate.token ) ||
    typeof candidate.createdAt !== 'string' ||
    Number.isNaN( Date.parse( candidate.createdAt ) )
  ) {
    return null;
  }
  return candidate as unknown as LockOwner;
}

async function readRecord(
  root: string,
  path: string,
  label: string
): Promise< LockRecord | null > {
  if ( ! isInside( path, root ) ) {
    throw new Error( `block generator lock is not root-local: ${ path }` );
  }
  try {
    const canonical = await realpath( path );
    if ( canonical !== path || ! isInside( canonical, root ) ) {
      throw new Error( `block generator lock is not root-local: ${ path }` );
    }
    const [ content, status ] = await Promise.all( [
      readFile( path, 'utf8' ),
      lstat( path ),
    ] );
    const owner = parseOwner( content );
    if ( owner === null ) {
      throw new InvalidLockRecordError( `invalid ${ label } record` );
    }
    return {
      content,
      device: status.dev,
      inode: status.ino,
      owner,
    };
  } catch ( error ) {
    if ( isErrno( error, 'ENOENT' ) ) {
      return null;
    }
    throw error;
  }
}

function recordsMatch( left: LockRecord, right: LockRecord ): boolean {
  return (
    left.content === right.content &&
    left.device === right.device &&
    left.inode === right.inode
  );
}

async function observeCompleteRecord(
  root: string,
  path: string,
  label: string,
  afterOwnerRead?: () => Promise< void > | void
): Promise< LockRecord | null > {
  const before = await readRecord( root, path, label );
  if ( before === null ) {
    return null;
  }
  await afterOwnerRead?.();

  let candidate: LockRecord | null = null;
  let candidateError: unknown;
  try {
    candidate = await readRecord(
      root,
      candidatePath( root, before.owner ),
      `${ label } candidate`
    );
  } catch ( error ) {
    candidateError = error;
  }

  let after: LockRecord | null;
  try {
    after = await readRecord( root, path, label );
  } catch ( error ) {
    if ( error instanceof InvalidLockRecordError ) {
      return null;
    }
    throw error;
  }
  if ( after === null || ! recordsMatch( before, after ) ) {
    return null;
  }
  if ( candidateError !== undefined ) {
    throw candidateError;
  }
  if ( candidate === null ) {
    throw new Error( `${ label } candidate is missing` );
  }
  if ( ! recordsMatch( before, candidate ) ) {
    throw new Error( `${ label } candidate does not match` );
  }
  return before;
}

function ownerIsLive( owner: LockOwner ): boolean {
  if ( owner.hostname !== hostname() ) {
    return true;
  }
  try {
    process.kill( owner.pid, 0 );
    return true;
  } catch ( error ) {
    return ! isErrno( error, 'ESRCH' );
  }
}

function flattenErrors( error: unknown ): unknown[] {
  return error instanceof AggregateError ? [ ...error.errors ] : [ error ];
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

async function release(
  root: string,
  handle: LockHandle,
  operations: LockOperations
): Promise< void > {
  const published = await readRecord( root, handle.path, 'published lock' );
  if ( published?.owner.token !== handle.owner.token ) {
    throw new BlockLockCleanupError(
      'refused to release a lock with a different owner token',
      [ new Error( 'block generator lock ownership changed' ) ],
      await remainingPaths( [ handle.path, handle.candidate ] )
    );
  }
  const remove = operations.remove ?? rm;
  try {
    await remove( handle.path, { force: true } );
  } catch ( error ) {
    throw new BlockLockCleanupError(
      'failed to remove the published block generator lock',
      [ error ],
      await remainingPaths( [ handle.path, handle.candidate ] )
    );
  }
  try {
    await remove( handle.candidate, { force: true } );
  } catch ( error ) {
    throw new BlockLockCleanupError(
      'failed to remove the block generator lock candidate',
      [ error ],
      await remainingPaths( [ handle.candidate ] )
    );
  }
}

async function discard(
  handle: LockHandle,
  error: unknown,
  operations: LockOperations
): Promise< never > {
  try {
    await ( operations.remove ?? rm )( handle.candidate, { force: true } );
  } catch ( cleanupError ) {
    throw new BlockLockCleanupError(
      'lock acquisition and candidate cleanup both failed',
      [ error, cleanupError ],
      await remainingPaths( [ handle.candidate ] )
    );
  }
  throw error;
}

async function removeStaleCandidate(
  root: string,
  stale: LockRecord,
  operations: LockOperations
): Promise< void > {
  const staleCandidate = candidatePath( root, stale.owner );
  try {
    await ( operations.remove ?? rm )( staleCandidate, { force: true } );
  } catch ( error ) {
    throw new BlockLockCleanupError(
      'failed to clean a stale lock candidate',
      [ error ],
      await remainingPaths( [ staleCandidate ] )
    );
  }
}

async function reclaim(
  root: string,
  stale: LockRecord,
  handle: LockHandle,
  operations: LockOperations
): Promise< boolean > {
  const claim = join( root, `${ lockName }.claim.${ stale.owner.token }` );
  let ownsClaim = false;
  try {
    await link( handle.candidate, claim );
    ownsClaim = true;
  } catch ( error ) {
    if ( ! isErrno( error, 'EEXIST' ) ) {
      throw error;
    }
  }

  if ( ! ownsClaim ) {
    const claimant = await observeCompleteRecord(
      root,
      claim,
      'stale-lock claim'
    );
    if ( claimant === null ) {
      return false;
    }
    if ( ownerIsLive( claimant.owner ) ) {
      return false;
    }
  }

  const current = await readRecord( root, handle.path, 'published lock' );
  if ( current?.content !== stale.content ) {
    if ( ownsClaim ) {
      try {
        await ( operations.remove ?? rm )( claim, { force: true } );
      } catch ( error ) {
        throw new BlockLockCleanupError(
          'stale-lock claim cleanup failed after owner changed',
          [ error ],
          await remainingPaths( [ claim ] )
        );
      }
    }
    return false;
  }

  await rename( claim, handle.path );
  try {
    await removeStaleCandidate( root, stale, operations );
  } catch ( cleanupError ) {
    if ( ownsClaim ) {
      try {
        await release( root, handle, operations );
      } catch ( releaseError ) {
        throw new BlockLockCleanupError(
          'stale-candidate and newly acquired lock cleanup both failed',
          [
            ...flattenErrors( cleanupError ),
            ...flattenErrors( releaseError ),
          ],
          [
            ...errorLeftovers( cleanupError ),
            ...errorLeftovers( releaseError ),
          ]
        );
      }
    }
    throw cleanupError;
  }
  return ownsClaim;
}

async function acquire(
  root: string,
  operations: LockOperations
): Promise< LockHandle > {
  const owner: LockOwner = {
    version: 1,
    pid: process.pid,
    hostname: hostname(),
    token: randomUUID(),
    createdAt: new Date().toISOString(),
  };
  const handle = {
    candidate: candidatePath( root, owner ),
    owner,
    path: join( root, lockName ),
  };
  try {
    await writeFile( handle.candidate, ownerContent( owner ), { flag: 'wx' } );
  } catch ( error ) {
    try {
      await ( operations.remove ?? rm )( handle.candidate, { force: true } );
    } catch ( cleanupError ) {
      throw new BlockLockCleanupError(
        'lock candidate write and cleanup both failed',
        [ error, cleanupError ],
        await remainingPaths( [ handle.candidate ] )
      );
    }
    throw error;
  }
  for (;;) {
    try {
      await link( handle.candidate, handle.path );
      return handle;
    } catch ( error ) {
      if ( ! isErrno( error, 'EEXIST' ) ) {
        return discard( handle, error, operations );
      }
    }
    try {
      const current = await observeCompleteRecord(
        root,
        handle.path,
        'published lock',
        operations.afterPublishedOwnerRead
      );
      if ( current !== null ) {
        if (
          ! ownerIsLive( current.owner ) &&
          ( await reclaim( root, current, handle, operations ) )
        ) {
          return handle;
        }
      }
    } catch ( error ) {
      return discard( handle, error, operations );
    }
    if ( Date.now() - Date.parse( owner.createdAt ) >= timeoutMilliseconds ) {
      return discard(
        handle,
        new Error( 'timed out waiting for the block generator lock' ),
        operations
      );
    }
    operations.onLockContention?.();
    await delay( retryMilliseconds );
  }
}

export async function withExclusiveBlockLock< Result >(
  root: string,
  operations: LockOperations,
  work: () => Promise< Result >
): Promise< Result > {
  const handle = await acquire( root, operations );
  let value: Result | undefined;
  let workError: unknown;
  try {
    value = await work();
  } catch ( error ) {
    workError = error;
  }
  let releaseError: unknown;
  try {
    await release( root, handle, operations );
  } catch ( error ) {
    releaseError = error;
  }
  if ( workError !== undefined && releaseError !== undefined ) {
    throw new BlockLockCleanupError(
      'block generation and lock cleanup both failed',
      [ ...flattenErrors( workError ), ...flattenErrors( releaseError ) ],
      [ ...errorLeftovers( workError ), ...errorLeftovers( releaseError ) ]
    );
  }
  if ( workError !== undefined ) {
    throw workError;
  }
  if ( releaseError !== undefined ) {
    throw releaseError;
  }
  return value as Result;
}
