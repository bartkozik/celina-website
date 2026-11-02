// @vitest-environment jsdom
import { act, cleanup, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  useNewsRequest,
  useNewsSettings,
  type NewsEditorSettings,
} from './editor-data';

const settings: NewsEditorSettings = {
  endpoint: 'https://example.test/subsite/?rest_route=/celina/v1/news-editor',
  nonce: 'editor-session',
  addUrl: null,
  manageUrl: null,
};
const storeFixture = vi.hoisted( () => ( { editorState: 'available' } ) );

vi.mock( '@wordpress/data', () => ( {
  useSelect: (
    mapSelect: ( select: ( store: string ) => unknown ) => unknown
  ) =>
    mapSelect( ( store ) => {
      if ( store === 'core/editor' ) {
        if ( storeFixture.editorState === 'missing-store' ) {
          return undefined;
        }
        if ( storeFixture.editorState === 'missing-method' ) {
          return {};
        }
        return { getEditorSettings: () => ( { celinaNews: settings } ) };
      }
      if ( store === 'core/block-editor' ) {
        // WordPress forwards only its supported block settings to this store.
        return { getSettings: () => ( {} ) };
      }
      throw new Error( `Unexpected editor store: ${ store }` );
    } ),
} ) );

const fetchMock = vi.fn< typeof fetch >();
const success = ( data: unknown ) =>
  new Response( JSON.stringify( data ), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  } );

describe( 'News editor requests', () => {
  beforeEach( () => {
    storeFixture.editorState = 'available';
    fetchMock.mockReset();
    vi.stubGlobal( 'fetch', fetchMock );
  } );
  afterEach( () => {
    cleanup();
    vi.unstubAllGlobals();
  } );

  it( 'reads custom server settings from the editor when block-editor filters them out', () => {
    const { result } = renderHook( () => useNewsSettings() );
    expect( result.current ).toBe( settings );
  } );

  it.each( [ 'missing-store', 'missing-method' ] )(
    'returns unavailable settings for a non-post editor (%s)',
    ( state ) => {
      storeFixture.editorState = state;
      const { result } = renderHook( () => useNewsSettings() );
      expect( result.current ).toBeUndefined();
    }
  );

  it( 'uses the server URL and nonce, preserving subdirectories and plain REST permalinks', async () => {
    fetchMock.mockResolvedValue( success( { posts: [] } ) );
    const { result } = renderHook( () =>
      useNewsRequest( settings, 'category=42&count=12' )
    );
    await waitFor( () => expect( result.current.loading ).toBe( false ) );
    const [ address, options ] = fetchMock.mock.calls[ 0 ]!;
    const url = new URL( String( address ) );
    expect( url.pathname ).toBe( '/subsite/' );
    expect( url.searchParams.get( 'rest_route' ) ).toBe(
      '/celina/v1/news-editor'
    );
    expect( url.searchParams.get( 'category' ) ).toBe( '42' );
    expect( options?.headers ).toEqual( { 'X-WP-Nonce': 'editor-session' } );
    expect( options?.credentials ).toBe( 'same-origin' );
    expect( options?.cache ).toBe( 'no-store' );
    expect( result.current.data ).toEqual( { posts: [] } );
    expect( result.current.error ).toBe( '' );
  } );

  it( 'discards a slow previous category response after the selection changes', async () => {
    let resolveFirst!: ( value: Response ) => void;
    fetchMock.mockImplementationOnce(
      () =>
        new Promise( ( resolve ) => {
          resolveFirst = resolve;
        } )
    );
    fetchMock.mockResolvedValueOnce( success( { posts: [ { id: 2 } ] } ) );
    const { result, rerender } = renderHook(
      ( { query } ) => useNewsRequest( settings, query ),
      { initialProps: { query: 'category=1' } }
    );
    expect( result.current.loading ).toBe( true );
    rerender( { query: 'category=2' } );
    await waitFor( () =>
      expect( result.current.data ).toEqual( { posts: [ { id: 2 } ] } )
    );
    await act( async () =>
      resolveFirst( success( { posts: [ { id: 1 } ] } ) )
    );
    expect( result.current.data ).toEqual( { posts: [ { id: 2 } ] } );
    expect( fetchMock.mock.calls[ 0 ]?.[ 1 ]?.signal?.aborted ).toBe( true );
  } );

  it( 'refreshes after an article is edited in another tab', async () => {
    fetchMock.mockResolvedValueOnce(
      success( { posts: [ { title: 'Before' } ] } )
    );
    fetchMock.mockResolvedValueOnce(
      success( { posts: [ { title: 'After' } ] } )
    );
    const { result } = renderHook( () =>
      useNewsRequest( settings, 'layout=grid' )
    );
    await waitFor( () =>
      expect( result.current.data ).toEqual( {
        posts: [ { title: 'Before' } ],
      } )
    );
    act( () => result.current.refresh() );
    await waitFor( () =>
      expect( result.current.data ).toEqual( { posts: [ { title: 'After' } ] } )
    );
  } );

  it.each( [ 401, 403 ] )(
    'reports session or permission failures (%s) without declaring articles unavailable',
    async ( status ) => {
      fetchMock.mockResolvedValue( new Response( '{}', { status } ) );
      const { result } = renderHook( () =>
        useNewsRequest( settings, 'postId=1001' )
      );
      await waitFor( () => expect( result.current.loading ).toBe( false ) );
      expect( result.current.error ).toContain( 'Save your page, then reload' );
      expect( result.current.data ).toBeNull();
    }
  );

  it( 'shows server errors separately from a successful empty result and can retry', async () => {
    fetchMock.mockResolvedValueOnce( new Response( '{}', { status: 500 } ) );
    fetchMock.mockResolvedValueOnce( success( { posts: [] } ) );
    const { result } = renderHook( () =>
      useNewsRequest( settings, 'mode=search&search=history' )
    );
    await waitFor( () =>
      expect( result.current.error ).toContain( 'Could not load articles' )
    );
    expect( result.current.data ).toBeNull();
    act( () => result.current.refresh() );
    await waitFor( () =>
      expect( result.current.data ).toEqual( { posts: [] } )
    );
    expect( result.current.error ).toBe( '' );
  } );

  it( 'does not fetch while the request is disabled', () => {
    const { result } = renderHook( () => useNewsRequest( settings, null ) );
    expect( fetchMock ).not.toHaveBeenCalled();
    expect( result.current.loading ).toBe( false );
  } );
} );
