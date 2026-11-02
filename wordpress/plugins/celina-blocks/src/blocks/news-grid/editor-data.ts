import { useSelect } from '@wordpress/data';
import { useEffect, useState } from 'react';

export interface NewsEditorSettings {
  endpoint: string;
  nonce: string;
  addUrl: string | null;
  manageUrl: string | null;
}

export interface NewsEditorPost {
  id: number;
  title: string;
  excerpt: string;
  date: string;
  dateTime: string;
  categories: string[];
  image: { url: string; alt: string } | null;
  editUrl: string | null;
}

export interface NewsPreviewResponse {
  featured: NewsEditorPost | null;
  posts: NewsEditorPost[];
  selectedUnavailable: boolean;
  navigationCategories: number[];
}

export interface NewsSearchResponse {
  posts: NewsEditorPost[];
  hasMore: boolean;
}

interface RequestState< Result > {
  data: Result | null;
  error: string;
  loading: boolean;
  key: string | null;
}

export function useNewsSettings(): NewsEditorSettings | undefined {
  return useSelect( ( select ) => {
    // Custom server settings are retained here; block-editor forwards an allowlist.
    const editor = select( 'core/editor' ) as
      | {
          getEditorSettings?: () =>
            | { celinaNews?: NewsEditorSettings }
            | undefined;
        }
      | undefined;
    return editor?.getEditorSettings?.()?.celinaNews;
  }, [] );
}

export function useNewsRequest< Result >(
  settings: NewsEditorSettings | undefined,
  query: string | null,
  revision = 0
) {
  const [ refreshCount, setRefreshCount ] = useState( 0 );
  const [ state, setState ] = useState< RequestState< Result > >( {
    data: null,
    error: '',
    loading: query !== null,
    key: null,
  } );
  const endpoint = settings?.endpoint;
  const nonce = settings?.nonce;
  const key = `${ endpoint }|${ nonce }|${ query }|${ revision }|${ refreshCount }`;

  useEffect( () => {
    if ( query === null ) {
      setState( { data: null, error: '', loading: false, key } );
      return;
    }
    if ( ! endpoint || ! nonce ) {
      setState( {
        data: null,
        error:
          'Article preview is unavailable. Reload the editor to reconnect.',
        loading: false,
        key,
      } );
      return;
    }
    const controller = new AbortController();
    setState( { data: null, error: '', loading: true, key } );
    const url = new URL( endpoint );
    new URLSearchParams( query ).forEach( ( value, name ) => {
      url.searchParams.append( name, value );
    } );
    fetch( url.toString(), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-WP-Nonce': nonce },
      signal: controller.signal,
    } )
      .then( async ( response ) => {
        if ( response.status === 401 || response.status === 403 ) {
          throw new Error(
            'Article access could not be verified. Your session may have expired or your permissions changed. Save your page, then reload the editor.'
          );
        }
        if ( ! response.ok ) {
          throw new Error( 'Could not load articles. Try Refresh articles.' );
        }
        return response.json() as Promise< Result >;
      } )
      .then( ( data ) => {
        if ( ! controller.signal.aborted ) {
          setState( { data, error: '', loading: false, key } );
        }
      } )
      .catch( ( error: unknown ) => {
        if ( ! controller.signal.aborted ) {
          setState( {
            data: null,
            error:
              error instanceof Error &&
              error.message.startsWith( 'Article access' )
                ? error.message
                : 'Could not load articles. Try Refresh articles.',
            loading: false,
            key,
          } );
        }
      } );
    return () => controller.abort();
  }, [ endpoint, nonce, query, key ] );

  return {
    ...( state.key === key
      ? state
      : { data: null, error: '', loading: query !== null } ),
    refresh: () => setRefreshCount( ( count ) => count + 1 ),
  };
}

export function newsPlainText( value: string ): string {
  const container = document.createElement( 'div' );
  container.innerHTML = value;
  return container.textContent ?? '';
}
