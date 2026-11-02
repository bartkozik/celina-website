import { describe, expect, it } from 'vitest';
import { runWithCleanup } from './run-with-cleanup';

describe( 'runWithCleanup', () => {
  it( 'returns the action result when both action and cleanup succeed', async () => {
    const result = await runWithCleanup(
      async () => 'result',
      async () => undefined
    );

    expect( result ).toBe( 'result' );
  } );

  it( 'rethrows the original action error when cleanup succeeds', async () => {
    const actionError = new Error( 'action failed' );

    await expect(
      runWithCleanup(
        async () => Promise.reject( actionError ),
        async () => undefined
      )
    ).rejects.toBe( actionError );
  } );

  it( 'throws the cleanup error when action succeeds', async () => {
    const cleanupError = new Error( 'cleanup failed' );

    await expect(
      runWithCleanup(
        async () => 'result',
        async () => Promise.reject( cleanupError )
      )
    ).rejects.toBe( cleanupError );
  } );

  it( 'aggregates action and cleanup errors in execution order', async () => {
    const actionError = new Error( 'action failed' );
    const cleanupError = new Error( 'cleanup failed' );

    try {
      await runWithCleanup(
        async () => Promise.reject( actionError ),
        async () => Promise.reject( cleanupError )
      );
    } catch ( error ) {
      expect( error ).toBeInstanceOf( AggregateError );
      expect( ( error as AggregateError ).message ).toBe(
        'Action and cleanup both failed.'
      );
      expect( ( error as AggregateError ).errors ).toHaveLength( 2 );
      expect( ( error as AggregateError ).errors[ 0 ] ).toBe( actionError );
      expect( ( error as AggregateError ).errors[ 1 ] ).toBe( cleanupError );

      return;
    }

    throw new Error( 'Expected runWithCleanup to throw.' );
  } );
} );
