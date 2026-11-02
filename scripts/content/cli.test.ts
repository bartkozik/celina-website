import { describe, expect, it, vi } from 'vitest';
import {
  ContentCliUsageError,
  buildContentCommand,
  parseContentArguments,
  runContentCli,
} from './cli.ts';

describe( 'managed content CLI', () => {
  it.each( [ 'plan', 'apply' ] as const )( 'accepts %s mode', ( mode ) => {
    expect( parseContentArguments( [ mode ] ) ).toEqual( { mode } );
    expect( buildContentCommand( { mode } ) ).toEqual( [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      '/var/www/html/celina-workbench/content/command.php',
      mode,
    ] );
  } );

  it.each( [
    'brand-pages',
    'offer-pages',
    'people-contact-pages',
    'news-resources',
    'legal-pages',
  ] )( 'accepts the %s group exactly once', ( group ) => {
    const parsed = parseContentArguments( [ 'plan', '--group', group ] );

    expect( parsed ).toEqual( { group, mode: 'plan' } );
    expect( buildContentCommand( parsed ) ).toEqual( [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      '/var/www/html/celina-workbench/content/command.php',
      'plan',
      group,
    ] );
  } );

  it.each( [
    [ [] ],
    [ [ 'preview' ] ],
    [ [ 'plan', '--group' ] ],
    [ [ 'plan', '--group', 'unknown' ] ],
    [ [ 'plan', '--group', 'brand-pages', '--group', 'offer-pages' ] ],
    [ [ 'plan', '--unexpected' ] ],
  ] )( 'rejects invalid arguments %j', ( args ) => {
    expect( () => parseContentArguments( args ) ).toThrow(
      ContentCliUsageError
    );
  } );

  it( 'does not spawn after a usage error and exits 2', () => {
    const run = vi.fn();
    const writeError = vi.fn();
    const exit = vi.fn();

    runContentCli( [ 'invalid' ], run, writeError, exit );

    expect( run ).not.toHaveBeenCalled();
    expect( writeError ).toHaveBeenCalledOnce();
    expect( exit ).toHaveBeenCalledWith( 2 );
  } );

  it( 'forwards a valid command once', () => {
    const run = vi.fn();

    runContentCli(
      [ 'apply', '--', '--group', 'news-resources' ],
      run,
      vi.fn(),
      vi.fn()
    );

    expect( run ).toHaveBeenCalledWith( [
      'exec',
      'wp-env',
      'run',
      'cli',
      'wp',
      'eval-file',
      '/var/www/html/celina-workbench/content/command.php',
      'apply',
      'news-resources',
    ] );
  } );
} );
