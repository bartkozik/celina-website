import { SelectControl, TextControl } from '@wordpress/components';
import { useEffect, useState } from 'react';
import {
  newsPlainText,
  useNewsRequest,
  type NewsEditorPost,
  type NewsEditorSettings,
  type NewsSearchResponse,
} from './editor-data';

interface FeaturedPickerProps {
  settings: NewsEditorSettings | undefined;
  category: number;
  postId: number;
  selected: NewsEditorPost | null;
  unavailable: boolean;
  revision: number;
  onChange: ( postId: number ) => void;
}

export default function FeaturedPicker( {
  settings,
  category,
  postId,
  selected,
  unavailable,
  revision,
  onChange,
}: FeaturedPickerProps ) {
  const [ input, setInput ] = useState( '' );
  const [ search, setSearch ] = useState( '' );
  const [ page, setPage ] = useState( 1 );
  useEffect( () => {
    const timeout = setTimeout( () => setSearch( input.trim() ), 300 );
    return () => clearTimeout( timeout );
  }, [ input ] );
  const query = new URLSearchParams( {
    mode: 'search',
    category: String( category ),
    search,
    page: String( page ),
  } ).toString();
  const result = useNewsRequest< NewsSearchResponse >(
    settings,
    query,
    revision
  );
  const searching = result.loading || input.trim() !== search;
  const candidates = new Map(
    ( result.data?.posts ?? [] ).map( ( post ) => [ post.id, post ] )
  );
  if ( selected?.id === postId ) {
    candidates.set( selected.id, selected );
  }
  const options = [
    { label: 'Latest matching post', value: '0' },
    ...( postId > 0 && ! candidates.has( postId )
      ? [
          {
            label: unavailable
              ? `Unavailable selected article #${ postId }`
              : `Selected article #${ postId }`,
            value: String( postId ),
          },
        ]
      : [] ),
    ...Array.from( candidates.values() ).map( ( post ) => ( {
      label: newsPlainText( post.title ) || 'Untitled article',
      value: String( post.id ),
    } ) ),
  ];

  return (
    <div className="celina-news-grid__featured-picker">
      <TextControl
        label="Search published articles"
        value={ input }
        onChange={ ( value ) => {
          setInput( value.slice( 0, 200 ) );
          setPage( 1 );
        } }
        help="Search all matching published articles, including older stories."
      />
      <SelectControl
        label="Featured story"
        value={ String( postId ) }
        options={ options }
        onChange={ ( value ) => onChange( Number( value ) ) }
      />
      { searching && <p role="status">Searching published articles…</p> }
      { result.error && <p role="alert">{ result.error }</p> }
      { result.data?.posts.length === 0 && ! searching && (
        <p>
          No published articles match this search. Try another title or
          category.
        </p>
      ) }
      { ( page > 1 || result.data?.hasMore ) && (
        <div className="celina-news-grid__editor-actions">
          <button
            disabled={ page === 1 || searching }
            onClick={ () => setPage( ( current ) => current - 1 ) }
            type="button"
          >
            Previous articles
          </button>
          <span>Page { page }</span>
          <button
            disabled={ ! result.data?.hasMore || searching }
            onClick={ () => setPage( ( current ) => current + 1 ) }
            type="button"
          >
            More articles
          </button>
        </div>
      ) }
    </div>
  );
}
