export async function runWithCleanup< Result >(
  action: () => Promise< Result >,
  cleanup: () => Promise< void >
): Promise< Result > {
  let actionFailed = false;
  let actionError: unknown;
  let result: Result;

  try {
    result = await action();
  } catch ( error ) {
    actionFailed = true;
    actionError = error;
  }

  let cleanupFailed = false;
  let cleanupError: unknown;

  try {
    await cleanup();
  } catch ( error ) {
    cleanupFailed = true;
    cleanupError = error;
  }

  if ( actionFailed && cleanupFailed ) {
    throw new AggregateError(
      [ actionError, cleanupError ],
      'Action and cleanup both failed.'
    );
  }
  if ( actionFailed ) {
    throw actionError;
  }
  if ( cleanupFailed ) {
    throw cleanupError;
  }

  return result!;
}
