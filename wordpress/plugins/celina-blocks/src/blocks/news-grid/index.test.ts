// @vitest-environment jsdom

import { readFileSync } from 'node:fs';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  within,
} from '@testing-library/react';
import { createElement, useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import metadata from './block.json';
import Edit from './edit';
import {
  clampNewsCount,
  normalizeNewsAttributes,
  normalizeNewsCategoryIds,
  normalizeNewsId,
  selectedPostIsUnavailable,
  type NewsAttributes,
} from './model';

interface CategoryRecord {
  id: number;
  name: string;
  parent: number;
  count: number;
}

interface PostRecord {
  id: number;
  title: { rendered: string };
}

interface EntityQuery {
  _fields?: string[];
  categories?: number;
  page?: number;
  per_page: number;
  status?: string;
}

const fixtures = vi.hoisted( () => ( {
  categories: null as CategoryRecord[] | null,
  categoryPages: {} as Record< number, CategoryRecord[] | null >,
  categoryTotalPages: undefined as number | null | undefined,
  posts: null as PostRecord[] | null,
  queries: [] as Array< { kind: string; name: string; query: EntityQuery } >,
  totalPageQueries: [] as Array< {
    kind: string;
    name: string;
    query: EntityQuery;
  } >,
  recordsRevision: 0,
  navigationIds: null as number[] | null,
  searchPostIds: null as number[] | null,
  image: null as { url: string; alt: string } | null,
  categoryError: false,
  canManage: true,
  requestError: '',
  selectedUnavailable: false,
  refresh: vi.fn(),
} ) );

vi.mock( '@wordpress/block-editor', async () => {
  const React = await import( 'react' );

  return {
    InspectorControls: ( { children }: { children?: React.ReactNode } ) =>
      React.createElement( 'aside', null, children ),
    RichText: ( {
      onChange,
      tagName = 'div',
      value = '',
    }: {
      onChange?: ( value: string ) => void;
      tagName?: string;
      value?: string;
    } ) =>
      React.createElement(
        tagName,
        {
          contentEditable: Boolean( onChange ),
          onInput: ( event: React.FormEvent< HTMLElement > ) =>
            onChange?.( event.currentTarget.textContent ?? '' ),
          suppressContentEditableWarning: true,
        },
        value
      ),
    useBlockProps: ( props = {} ) => props,
  };
} );

vi.mock( '@wordpress/components', async () => {
  const React = await import( 'react' );

  return {
    TextControl: ( {
      label,
      onChange,
      value,
    }: {
      label: string;
      onChange: ( value: string ) => void;
      value: string;
    } ) =>
      React.createElement( 'input', {
        'aria-label': label,
        value,
        onChange: ( event: React.ChangeEvent< HTMLInputElement > ) =>
          onChange( event.target.value ),
      } ),
    PanelBody: ( {
      children,
      title,
    }: {
      children?: React.ReactNode;
      title: string;
    } ) => React.createElement( 'section', { 'aria-label': title }, children ),
    RangeControl: ( {
      label,
      max,
      min,
      onChange,
      value,
    }: {
      label: string;
      max: number;
      min: number;
      onChange: ( value: number | undefined ) => void;
      value: number;
    } ) =>
      React.createElement( 'input', {
        'aria-label': label,
        max,
        min,
        onChange: ( event: React.ChangeEvent< HTMLInputElement > ) =>
          onChange( Number( event.target.value ) ),
        type: 'range',
        value,
      } ),
    SelectControl: ( {
      label,
      onChange,
      options,
      value,
    }: {
      label: string;
      onChange: ( value: string ) => void;
      options: Array< { label: string; value: string } >;
      value: string;
    } ) =>
      React.createElement(
        'label',
        null,
        label,
        React.createElement(
          'select',
          {
            'aria-label': label,
            onChange: ( event: React.ChangeEvent< HTMLSelectElement > ) =>
              onChange( event.target.value ),
            value,
          },
          options.map( ( option ) =>
            React.createElement(
              'option',
              { key: option.value, value: option.value },
              option.label
            )
          )
        )
      ),
    Spinner: () => React.createElement( 'svg', { 'aria-hidden': true } ),
    ToggleControl: ( {
      checked,
      label,
      onChange,
    }: {
      checked: boolean;
      label: string;
      onChange: ( value: boolean ) => void;
    } ) =>
      React.createElement( 'input', {
        'aria-label': label,
        checked,
        onChange: ( event: React.ChangeEvent< HTMLInputElement > ) =>
          onChange( event.target.checked ),
        type: 'checkbox',
      } ),
  };
} );

vi.mock( '@wordpress/data', async () => {
  const React = await import( 'react' );

  type CoreDataSelectors = {
    getResolutionError: () => unknown;
    getEntityRecords: (
      kind: string,
      name: string,
      query: EntityQuery
    ) => CategoryRecord[] | PostRecord[] | null;
    getEntityRecordsTotalPages: (
      kind: string,
      name: string,
      query: EntityQuery
    ) => number | null;
  };
  type MapSelect = (
    select: ( store: string ) => CoreDataSelectors
  ) => unknown;

  const haveSameDependencies = (
    previous: readonly unknown[],
    next: readonly unknown[]
  ) =>
    previous.length === next.length &&
    previous.every( ( dependency, index ) => dependency === next[ index ] );
  const coreDataSelectors: CoreDataSelectors = {
    getResolutionError: () =>
      fixtures.categoryError ? new Error( 'Unavailable' ) : null,
    getEntityRecords: ( kind: string, name: string, query: EntityQuery ) => {
      fixtures.queries.push( { kind, name, query } );
      if ( kind !== 'taxonomy' ) {
        return fixtures.posts;
      }

      if (
        query.page !== undefined &&
        Object.hasOwn( fixtures.categoryPages, query.page )
      ) {
        return fixtures.categoryPages[ query.page ] ?? null;
      }

      return fixtures.categories;
    },
    getEntityRecordsTotalPages: ( kind, name, query ) => {
      fixtures.totalPageQueries.push( { kind, name, query } );
      if ( fixtures.categoryTotalPages !== undefined ) {
        return fixtures.categoryTotalPages;
      }

      return fixtures.categories === null ? null : 1;
    },
  };

  return {
    useSelect: (
      mapSelect: MapSelect,
      dependencies: readonly unknown[] = []
    ) => {
      const selection = React.useRef< {
        dependencies: readonly unknown[];
        recordsRevision: number;
        value: unknown;
      } | null >( null );

      if (
        selection.current === null ||
        selection.current.recordsRevision !== fixtures.recordsRevision ||
        ! haveSameDependencies( selection.current.dependencies, dependencies )
      ) {
        selection.current = {
          dependencies: [ ...dependencies ],
          recordsRevision: fixtures.recordsRevision,
          value: mapSelect( () => coreDataSelectors ),
        };
      }

      return selection.current.value;
    },
  };
} );

vi.mock( './editor-data', async ( importOriginal ) => ( {
  ...( await importOriginal< typeof import('./editor-data') >() ),
  useNewsSettings: () => ( {
    endpoint: 'https://example.test/subsite/wp-json/celina/v1/news-editor',
    nonce: 'test-nonce',
    addUrl: fixtures.canManage
      ? 'https://example.test/subsite/wp-admin/post-new.php'
      : null,
    manageUrl: fixtures.canManage
      ? 'https://example.test/subsite/wp-admin/edit.php'
      : null,
  } ),
  useNewsRequest: ( _settings: unknown, query: string | null ) => {
    if ( ! query ) {
      return {
        data: null,
        loading: false,
        error: '',
        refresh: fixtures.refresh,
      };
    }
    const params = new URLSearchParams( query );
    const posts =
      fixtures.posts?.map( ( post ) => ( {
        ...post,
        title: post.title.rendered,
        excerpt: 'Article summary.',
        date: 'September 21, 2026',
        dateTime: '2026-09-21T12:00:00',
        categories: [ 'Research' ],
        image: fixtures.image,
        editUrl: fixtures.canManage
          ? `https://example.test/subsite/wp-admin/post.php?post=${ post.id }&action=edit`
          : null,
      } ) ) ?? null;
    const featured =
      params.get( 'layout' ) === 'grid'
        ? null
        : posts?.find(
            ( post ) => post.id === Number( params.get( 'postId' ) )
          ) ??
          posts?.[ 0 ] ??
          null;
    let displayedPosts = posts;
    if ( posts && params.get( 'mode' ) === 'search' ) {
      displayedPosts = fixtures.searchPostIds
        ? posts.filter(
            ( post ) => fixtures.searchPostIds?.includes( post.id )
          )
        : posts;
    } else if ( params.get( 'layout' ) === 'featured' ) {
      displayedPosts = [];
    } else if ( posts ) {
      displayedPosts = posts
        .filter( ( post ) => post.id !== featured?.id )
        .slice(
          0,
          params.get( 'layout' ) === 'news-index'
            ? 6
            : Number( params.get( 'count' ) )
        );
    }
    return {
      data: posts
        ? {
            featured,
            posts: displayedPosts,
            selectedUnavailable: fixtures.selectedUnavailable,
            navigationCategories:
              fixtures.navigationIds ??
              params.getAll( 'navigationCategories[]' ).map( Number ),
            hasMore: false,
          }
        : null,
      error: fixtures.requestError,
      loading: posts === null && ! fixtures.requestError,
      refresh: fixtures.refresh,
    };
  },
} ) );

function ControlledEdit( {
  initialAttributes,
}: {
  initialAttributes: Partial< NewsAttributes >;
} ) {
  const [ attributes, setAttributes ] = useState( initialAttributes );

  return createElement(
    'div',
    null,
    createElement( Edit, {
      attributes,
      setAttributes: ( next ) =>
        setAttributes( ( current ) => ( { ...current, ...next } ) ),
    } ),
    createElement(
      'output',
      { 'data-testid': 'current-news-attributes' },
      JSON.stringify( attributes )
    )
  );
}

function getCurrentAttributes(): Partial< NewsAttributes > {
  return JSON.parse(
    screen.getByTestId( 'current-news-attributes' ).textContent ?? '{}'
  ) as Partial< NewsAttributes >;
}

function getOrderedCategoryNames(): string[] {
  return within( screen.getByRole( 'list' ) )
    .getAllByRole( 'listitem' )
    .map( ( item ) => item.firstElementChild?.textContent ?? '' );
}

describe( 'News Grid', () => {
  afterEach( cleanup );

  beforeEach( () => {
    fixtures.categories = null;
    fixtures.categoryPages = {};
    fixtures.categoryTotalPages = undefined;
    fixtures.posts = null;
    fixtures.queries = [];
    fixtures.totalPageQueries = [];
    fixtures.recordsRevision = 0;
    fixtures.categoryError = false;
    fixtures.navigationIds = null;
    fixtures.searchPostIds = null;
    fixtures.image = null;
    fixtures.canManage = true;
    fixtures.requestError = '';
    fixtures.selectedUnavailable = false;
    fixtures.refresh.mockClear();
  } );

  it( 'explains when a selected navigation category is omitted from the public page', () => {
    fixtures.posts = [];
    fixtures.categories = [
      { id: 9, name: 'Empty category', parent: 0, count: 0 },
    ];
    fixtures.navigationIds = [];
    render(
      createElement( Edit, {
        attributes: { layout: 'news-index', navigationCategories: [ 9 ] },
        setAttributes: vi.fn(),
      } )
    );
    expect(
      screen.getByText( /Not shown on the published page: Empty category/ )
    ).not.toBeNull();
    expect(
      screen.getByText(
        /The default category and categories without published articles/
      )
    ).not.toBeNull();
    expect(
      (
        screen.getByRole( 'checkbox', {
          name: 'Empty category',
        } ) as HTMLInputElement
       ).checked
    ).toBe( true );
  } );

  it( 'reports failed category requests without declaring saved navigation invalid', () => {
    fixtures.categoryError = true;
    render(
      createElement( Edit, {
        attributes: {
          category: 42,
          layout: 'news-index',
          navigationCategories: [ 42 ],
        },
        setAttributes: vi.fn(),
      } )
    );
    expect(
      screen.getByRole( 'alert', { name: 'Category loading error' } )
        .textContent
    ).toContain( 'Could not load categories' );
    expect(
      screen.queryByRole( 'alert', { name: 'Invalid saved navigation' } )
    ).toBeNull();
    expect(
      (
        screen.getByRole( 'combobox', {
          name: 'Category',
        } ) as HTMLSelectElement
       ).value
    ).toBe( '42' );
  } );

  it( 'offers safe article management links and publication guidance', () => {
    render( createElement( Edit, { attributes: {}, setAttributes: vi.fn() } ) );
    const add = screen.getByRole( 'link', { name: /Add article/ } );
    expect( add.getAttribute( 'href' ) ).toBe(
      'https://example.test/subsite/wp-admin/post-new.php'
    );
    expect( add.getAttribute( 'target' ) ).toBe( '_blank' );
    expect( add.getAttribute( 'rel' ) ).toContain( 'noopener' );
    expect(
      screen
        .getByRole( 'link', { name: /Manage articles/ } )
        .getAttribute( 'href' )
    ).toBe( 'https://example.test/subsite/wp-admin/edit.php' );
    expect(
      screen.getByText( /Published articles appear automatically/ )
    ).not.toBeNull();
    expect(
      screen.getByRole( 'button', { name: 'Refresh articles' } )
    ).not.toBeNull();
  } );

  it( 'aligns excerpts below a two-line desktop title area', () => {
    const styles = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/news-grid/style.scss',
      'utf8'
    );

    expect( styles ).toContain(
      '.celina-news-grid__items .celina-news-card {'
    );
    expect( styles ).toContain( 'display: flex;' );
    expect( styles ).toContain( 'flex-direction: column;' );
    expect( styles ).toContain( 'min-block-size: 2.4em;' );
    expect( styles ).toContain( 'min-block-size: 0;' );
  } );

  it( 'keeps the editor category navigation readable and keyboard focused', () => {
    const styles = readFileSync(
      'wordpress/plugins/celina-blocks/src/blocks/news-grid/editor.scss',
      'utf8'
    );

    expect( styles ).toContain( '.celina-news-grid__navigation-control {' );
    expect( styles ).toContain( 'var(--celina-space-2, 0.5rem)' );
    expect( styles ).toContain( 'var(--celina-color-text, #1a1a1a)' );
    expect( styles ).toContain( 'input:focus-visible {' );
    expect( styles ).toContain(
      'outline: 3px solid var(--celina-color-citrus-dark, #7e2d2b);'
    );
  } );

  it( 'declares the dynamic News Grid metadata contract', () => {
    expect( metadata ).toEqual( {
      apiVersion: 2,
      name: 'celina/news-grid',
      version: '0.1.0',
      title: 'News Grid — REDESIGNED',
      category: 'celina-redesigned',
      icon: 'grid-view',
      textdomain: 'celina-blocks',
      attributes: {
        heading: { type: 'string', default: 'Latest News' },
        category: { type: 'number', default: 0 },
        count: { type: 'number', default: 3 },
        layout: {
          type: 'string',
          enum: [ 'grid', 'featured', 'news-index' ],
          default: 'grid',
        },
        postId: { type: 'number', default: 0 },
        pagination: { type: 'boolean', default: false },
        navigationCategories: {
          type: 'array',
          default: [],
          items: { type: 'number' },
        },
      },
      supports: { html: false, anchor: true },
      editorScript: 'file:./index.js',
      editorStyle: 'file:./index.css',
      style: 'file:./style-index.css',
      render: 'file:./render.php',
    } );
  } );

  it( 'normalizes legacy and malformed attributes without invalidating saved grids', () => {
    expect( normalizeNewsAttributes( {} ) ).toEqual( {
      heading: 'Latest News',
      category: 0,
      count: 3,
      layout: 'grid',
      postId: 0,
      pagination: false,
      navigationCategories: [],
    } );
    expect( normalizeNewsCategoryIds( [ 4, 2, 4, -1, 3.8, '5' ] ) ).toEqual( [
      4, 2, 3,
    ] );
    expect( normalizeNewsAttributes( {} ).navigationCategories ).toEqual( [] );
    expect( clampNewsCount( 0 ) ).toBe( 1 );
    expect( clampNewsCount( 99 ) ).toBe( 12 );
    expect( normalizeNewsId( -4 ) ).toBe( 0 );
    expect( normalizeNewsId( 7.9 ) ).toBe( 7 );
    expect(
      normalizeNewsAttributes( {
        layout: 'invalid' as 'grid',
        pagination: true,
      } )
    ).toMatchObject( { layout: 'grid', pagination: true } );
    expect(
      normalizeNewsAttributes( { layout: 'featured', pagination: true } )
    ).toMatchObject( { layout: 'featured', pagination: false } );
  } );

  it( 'recognizes an unavailable explicit featured candidate', () => {
    const posts = [ { id: 7, title: { rendered: 'Matching story' } } ];

    expect( selectedPostIsUnavailable( 0, posts ) ).toBe( false );
    expect( selectedPostIsUnavailable( 7, posts ) ).toBe( false );
    expect( selectedPostIsUnavailable( 8, posts ) ).toBe( true );
    expect( selectedPostIsUnavailable( 8, null ) ).toBe( false );
  } );

  it( 'offers category-filtered published feature candidates and warns on fallback', () => {
    fixtures.posts = [ { id: 5, title: { rendered: 'Published candidate' } } ];
    fixtures.selectedUnavailable = true;

    render(
      createElement( Edit, {
        attributes: {
          heading: 'News',
          category: 42,
          count: 3,
          layout: 'featured',
          postId: 99,
          pagination: true,
        },
        setAttributes: vi.fn(),
      } )
    );

    expect(
      screen.getByRole( 'combobox', { name: 'Featured story' } )
    ).not.toBeNull();
    expect( screen.getByRole( 'alert' ).textContent ).toContain(
      'latest matching published post'
    );
    expect(
      screen.queryByRole( 'checkbox', { name: 'Show pagination' } )
    ).toBeNull();
  } );

  it( 'shows pagination only for grid and explains the composite news index', () => {
    fixtures.posts = [];
    const { rerender } = render(
      createElement( Edit, {
        attributes: {
          heading: 'News',
          category: 0,
          count: 3,
          layout: 'grid',
          postId: 0,
          pagination: false,
        },
        setAttributes: vi.fn(),
      } )
    );

    expect(
      screen.getByRole( 'checkbox', { name: 'Show pagination' } )
    ).not.toBeNull();

    rerender(
      createElement( Edit, {
        attributes: {
          heading: 'News',
          category: 0,
          count: 3,
          layout: 'news-index',
          postId: 0,
          pagination: false,
        },
        setAttributes: vi.fn(),
      } )
    );

    expect(
      screen.queryByRole( 'checkbox', { name: 'Show pagination' } )
    ).toBeNull();
    expect(
      screen.getByText( /deduplicated, paginated list of six latest stories/ )
    ).not.toBeNull();
  } );

  it( 'shows ordered category navigation controls only for the news index', () => {
    fixtures.categories = [ { id: 4, name: 'Research', parent: 0, count: 3 } ];
    fixtures.posts = [];
    const view = render(
      createElement( Edit, {
        attributes: { layout: 'grid', navigationCategories: [] },
        setAttributes: vi.fn(),
      } )
    );

    expect( screen.queryByRole( 'checkbox', { name: 'Research' } ) ).toBeNull();
    expect( fixtures.queries ).toContainEqual( {
      kind: 'taxonomy',
      name: 'category',
      query: {
        _fields: [ 'id', 'name', 'parent', 'count' ],
        page: 1,
        per_page: 100,
      },
    } );
    expect( fixtures.totalPageQueries ).toContainEqual( {
      kind: 'taxonomy',
      name: 'category',
      query: {
        _fields: [ 'id', 'name', 'parent', 'count' ],
        page: 1,
        per_page: 100,
      },
    } );

    view.rerender(
      createElement( Edit, {
        attributes: { layout: 'featured', navigationCategories: [] },
        setAttributes: vi.fn(),
      } )
    );
    expect( screen.queryByRole( 'checkbox', { name: 'Research' } ) ).toBeNull();

    view.rerender(
      createElement( Edit, {
        attributes: { layout: 'news-index', navigationCategories: [] },
        setAttributes: vi.fn(),
      } )
    );

    expect(
      screen.getByRole( 'group', { name: 'Category navigation' } )
    ).not.toBeNull();
    expect(
      screen.getByRole( 'checkbox', { name: 'Research' } )
    ).not.toBeNull();
  } );

  it( 'appends selected categories in click order without duplicates and removes unchecked categories', () => {
    fixtures.categories = [
      { id: 4, name: 'Research', parent: 0, count: 3 },
      { id: 2, name: 'Community', parent: 0, count: 4 },
      { id: 3, name: 'Careers', parent: 0, count: 2 },
    ];
    fixtures.posts = [];
    render(
      createElement( ControlledEdit, {
        initialAttributes: {
          layout: 'news-index',
          navigationCategories: [ 4, 2, 4 ],
        },
      } )
    );

    expect(
      (
        screen.getByRole( 'checkbox', {
          name: 'Research',
        } ) as HTMLInputElement
       ).checked
    ).toBe( true );
    expect(
      (
        screen.getByRole( 'checkbox', {
          name: 'Community',
        } ) as HTMLInputElement
       ).checked
    ).toBe( true );
    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Careers' } ) );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [
      4, 2, 3,
    ] );
    expect( getOrderedCategoryNames() ).toEqual( [
      'Research',
      'Community',
      'Careers',
    ] );

    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Community' } ) );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [ 4, 3 ] );
    expect( getOrderedCategoryNames() ).toEqual( [ 'Research', 'Careers' ] );
  } );

  it( 'moves selected categories cumulatively, announces the result, and retains focus', () => {
    fixtures.categories = [
      { id: 4, name: 'Research', parent: 0, count: 3 },
      { id: 2, name: 'Community', parent: 0, count: 4 },
      { id: 3, name: 'Careers', parent: 0, count: 2 },
    ];
    fixtures.posts = [];
    render(
      createElement( ControlledEdit, {
        initialAttributes: {
          layout: 'news-index',
          navigationCategories: [ 4, 2, 3 ],
        },
      } )
    );

    expect(
      (
        screen.getByRole( 'button', {
          name: 'Move Research up',
        } ) as HTMLButtonElement
       ).disabled
    ).toBe( true );
    expect(
      (
        screen.getByRole( 'button', {
          name: 'Move Careers down',
        } ) as HTMLButtonElement
       ).disabled
    ).toBe( true );

    const moveResearchDown = screen.getByRole( 'button', {
      name: 'Move Research down',
    } );
    moveResearchDown.focus();
    fireEvent.click( moveResearchDown );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [
      2, 4, 3,
    ] );
    expect( getOrderedCategoryNames() ).toEqual( [
      'Community',
      'Research',
      'Careers',
    ] );
    expect(
      screen.getByRole( 'status', { name: 'Category order update' } )
        .textContent
    ).toBe( 'Research moved to position 2 of 3.' );
    expect( moveResearchDown.ownerDocument.activeElement ).toBe(
      moveResearchDown
    );

    fireEvent.click( moveResearchDown );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [
      2, 3, 4,
    ] );
    expect( getOrderedCategoryNames() ).toEqual( [
      'Community',
      'Careers',
      'Research',
    ] );
  } );

  it( 'combines explicit taxonomy pages so a child validates through an ancestor from page one', () => {
    fixtures.categoryPages = {
      1: [ { id: 10, name: 'News', parent: 0, count: 8 } ],
      2: [ { id: 12, name: 'Neighborhood', parent: 10, count: 2 } ],
    };
    fixtures.categoryTotalPages = 2;
    fixtures.posts = [];

    render(
      createElement( ControlledEdit, {
        initialAttributes: {
          category: 10,
          layout: 'news-index',
          navigationCategories: [],
        },
      } )
    );

    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Neighborhood' } ) );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [ 12 ] );
    expect(
      fixtures.queries.filter( ( query ) => query.kind === 'taxonomy' )
    ).toEqual( [
      {
        kind: 'taxonomy',
        name: 'category',
        query: {
          _fields: [ 'id', 'name', 'parent', 'count' ],
          page: 1,
          per_page: 100,
        },
      },
      {
        kind: 'taxonomy',
        name: 'category',
        query: {
          _fields: [ 'id', 'name', 'parent', 'count' ],
          page: 2,
          per_page: 100,
        },
      },
    ] );
  } );

  it( 'keeps the graph unresolved while a later taxonomy page is loading', () => {
    fixtures.categoryPages = {
      1: [ { id: 10, name: 'News', parent: 0, count: 8 } ],
      2: null,
    };
    fixtures.categoryTotalPages = 2;
    fixtures.posts = [];
    const initialAttributes = {
      category: 10,
      layout: 'news-index' as const,
      navigationCategories: [ 12 ],
    };
    const view = render(
      createElement( ControlledEdit, { initialAttributes } )
    );

    expect(
      screen.queryByRole( 'checkbox', { name: 'Neighborhood' } )
    ).toBeNull();
    expect(
      screen.queryByRole( 'alert', { name: 'Invalid saved navigation' } )
    ).toBeNull();

    fixtures.categoryPages[ 2 ] = [
      { id: 12, name: 'Neighborhood', parent: 10, count: 2 },
    ];
    fixtures.recordsRevision += 1;
    view.rerender( createElement( ControlledEdit, { initialAttributes } ) );

    expect(
      screen.getByRole( 'checkbox', { name: 'Neighborhood' } )
    ).not.toBeNull();
    expect(
      screen.queryByRole( 'alert', { name: 'Invalid saved navigation' } )
    ).toBeNull();
  } );

  it( 'handles unresolved and zero taxonomy totals without requesting invalid pages', () => {
    fixtures.categoryPages = { 1: [] };
    fixtures.categoryTotalPages = null;
    fixtures.posts = [];
    const initialAttributes = {
      layout: 'news-index' as const,
      navigationCategories: [ 999 ],
    };
    const view = render(
      createElement( ControlledEdit, { initialAttributes } )
    );

    expect(
      fixtures.queries.filter( ( query ) => query.kind === 'taxonomy' )
    ).toHaveLength( 1 );
    expect(
      screen.queryByRole( 'alert', { name: 'Invalid saved navigation' } )
    ).toBeNull();

    fixtures.categoryTotalPages = 0;
    fixtures.recordsRevision += 1;
    view.rerender( createElement( ControlledEdit, { initialAttributes } ) );

    expect(
      fixtures.queries.filter( ( query ) => query.kind === 'taxonomy' )
    ).toHaveLength( 2 );
    expect(
      screen.getByRole( 'alert', { name: 'Invalid saved navigation' } )
    ).not.toBeNull();
  } );

  it( 'allows cumulative base and descendant selections while rejecting incomplete or cyclic paths', () => {
    fixtures.categories = [
      { id: 10, name: 'News', parent: 0, count: 8 },
      { id: 11, name: 'Local', parent: 10, count: 4 },
      { id: 12, name: 'Neighborhood', parent: 11, count: 2 },
      { id: 20, name: 'Careers', parent: 0, count: 3 },
      { id: 30, name: 'Orphaned', parent: 999, count: 1 },
      { id: 40, name: 'Cycle A', parent: 41, count: 1 },
      { id: 41, name: 'Cycle B', parent: 40, count: 1 },
    ];
    fixtures.posts = [];
    render(
      createElement( ControlledEdit, {
        initialAttributes: {
          category: 10,
          layout: 'news-index',
          navigationCategories: [],
        },
      } )
    );

    fireEvent.click( screen.getByRole( 'checkbox', { name: 'News' } ) );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [ 10 ] );
    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Neighborhood' } ) );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [ 10, 12 ] );

    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Careers' } ) );
    expect(
      screen.getByRole( 'alert', { name: 'Category selection error' } )
        .textContent
    ).toContain( 'Careers is outside the selected base category' );
    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Orphaned' } ) );
    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Cycle A' } ) );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [ 10, 12 ] );
  } );

  it( 'preserves invalid saved categories after base tightening and lets editors remove them', () => {
    fixtures.categories = [
      { id: 10, name: 'News', parent: 0, count: 8 },
      { id: 20, name: 'Careers', parent: 0, count: 3 },
    ];
    fixtures.posts = [];
    render(
      createElement( ControlledEdit, {
        initialAttributes: {
          category: 0,
          layout: 'news-index',
          navigationCategories: [ 10, 20 ],
        },
      } )
    );

    fireEvent.change( screen.getByRole( 'combobox', { name: 'Category' } ), {
      target: { value: '20' },
    } );
    expect( getCurrentAttributes() ).toMatchObject( {
      category: 20,
      navigationCategories: [ 10, 20 ],
    } );
    expect(
      screen.getByRole( 'alert', { name: 'Invalid saved navigation' } )
        .textContent
    ).toContain( 'News' );

    fireEvent.click(
      screen.getByRole( 'button', {
        name: 'Remove News from navigation',
      } )
    );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [ 20 ] );
    expect(
      screen.queryByRole( 'alert', { name: 'Invalid saved navigation' } )
    ).toBeNull();
  } );

  it( 'warns after category resolution and allows unresolved saved IDs to be removed', () => {
    fixtures.categories = null;
    fixtures.posts = [];
    const initialAttributes = {
      category: 0,
      layout: 'news-index' as const,
      navigationCategories: [ 999 ],
    };
    const view = render(
      createElement( ControlledEdit, { initialAttributes } )
    );

    expect(
      screen.queryByRole( 'alert', { name: 'Invalid saved navigation' } )
    ).toBeNull();

    fixtures.categories = [];
    fixtures.recordsRevision += 1;
    view.rerender( createElement( ControlledEdit, { initialAttributes } ) );

    expect(
      screen.getByRole( 'alert', { name: 'Invalid saved navigation' } )
        .textContent
    ).toContain( 'Category #999' );
    fireEvent.click(
      screen.getByRole( 'button', {
        name: 'Remove Category #999 from navigation',
      } )
    );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [] );
    expect( screen.queryByText( 'Category #999' ) ).toBeNull();
  } );

  it( 'clears transient selection errors when layout, base, or taxonomy data changes', () => {
    fixtures.categories = [
      { id: 10, name: 'News', parent: 0, count: 8 },
      { id: 20, name: 'Careers', parent: 0, count: 3 },
    ];
    fixtures.posts = [];
    const initialAttributes = {
      category: 10,
      layout: 'news-index' as const,
      navigationCategories: [],
    };
    const view = render(
      createElement( ControlledEdit, { initialAttributes } )
    );

    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Careers' } ) );
    expect(
      screen.getByRole( 'alert', { name: 'Category selection error' } )
    ).not.toBeNull();
    fireEvent.change( screen.getByRole( 'combobox', { name: 'Layout' } ), {
      target: { value: 'featured' },
    } );
    fireEvent.change( screen.getByRole( 'combobox', { name: 'Layout' } ), {
      target: { value: 'news-index' },
    } );
    expect(
      screen.queryByRole( 'alert', { name: 'Category selection error' } )
    ).toBeNull();

    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Careers' } ) );
    fireEvent.change( screen.getByRole( 'combobox', { name: 'Category' } ), {
      target: { value: '0' },
    } );
    expect(
      screen.queryByRole( 'alert', { name: 'Category selection error' } )
    ).toBeNull();

    fireEvent.change( screen.getByRole( 'combobox', { name: 'Category' } ), {
      target: { value: '10' },
    } );
    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Careers' } ) );
    fixtures.categories = [
      { id: 10, name: 'News', parent: 0, count: 8 },
      { id: 20, name: 'Careers', parent: 10, count: 3 },
    ];
    fixtures.recordsRevision += 1;
    view.rerender( createElement( ControlledEdit, { initialAttributes } ) );
    expect(
      screen.queryByRole( 'alert', { name: 'Category selection error' } )
    ).toBeNull();
    fireEvent.click( screen.getByRole( 'checkbox', { name: 'Careers' } ) );
    expect( getCurrentAttributes().navigationCategories ).toEqual( [ 20 ] );
  } );

  it( 'shows category settings and a loading state while published posts resolve', () => {
    fixtures.categories = [ { id: 42, name: 'Research', parent: 0, count: 3 } ];
    const setAttributes = vi.fn();

    render(
      createElement( Edit, {
        attributes: { heading: 'Latest News', category: 0, count: 3 },
        setAttributes,
      } )
    );

    expect(
      screen.getByRole( 'region', { name: 'News settings' } )
    ).not.toBeNull();
    expect(
      screen.getByRole( 'option', { name: 'All categories' } )
    ).not.toBeNull();
    expect( screen.getByRole( 'option', { name: 'Research' } ) ).not.toBeNull();
    expect(
      screen.getByRole( 'slider', { name: 'Posts' } ).getAttribute( 'min' )
    ).toBe( '1' );
    expect(
      screen.getByRole( 'slider', { name: 'Posts' } ).getAttribute( 'max' )
    ).toBe( '12' );
    expect( screen.getByRole( 'status' ).textContent ).toBe(
      'Loading published posts'
    );
    expect( setAttributes ).not.toHaveBeenCalled();
  } );

  it( 'explains empty results and gives an actionable next step', () => {
    fixtures.posts = [];
    render(
      createElement( Edit, {
        attributes: { category: 7 },
        setAttributes: vi.fn(),
      } )
    );
    expect( screen.getByText( /No published articles match/ ) ).not.toBeNull();
    expect(
      screen.getByText( /Publish an article in the selected category/ )
    ).not.toBeNull();
  } );

  it( 'renders one featured card, a bounded grid, and a deduplicated news index', () => {
    fixtures.posts = Array.from( { length: 10 }, ( _, index ) => ( {
      id: index + 1,
      title: { rendered: `Article ${ index + 1 }` },
    } ) );
    const view = render(
      createElement( Edit, {
        attributes: { layout: 'featured', postId: 8 },
        setAttributes: vi.fn(),
      } )
    );
    expect( screen.getAllByRole( 'article' ) ).toHaveLength( 1 );
    expect(
      screen.getByRole( 'heading', { name: 'Article 8' } )
    ).not.toBeNull();
    view.rerender(
      createElement( Edit, {
        attributes: { layout: 'news-index', postId: 8 },
        setAttributes: vi.fn(),
      } )
    );
    expect( screen.getAllByRole( 'article' ) ).toHaveLength( 7 );
    expect( screen.getAllByText( 'Article summary.' ) ).toHaveLength( 1 );
    expect(
      screen.getAllByRole( 'heading', { name: 'Article 8' } )
    ).toHaveLength( 1 );
    view.rerender(
      createElement( Edit, {
        attributes: { layout: 'grid', count: 2 },
        setAttributes: vi.fn(),
      } )
    );
    expect( screen.getAllByRole( 'article' ) ).toHaveLength( 2 );
  } );

  it( 'shows card details, missing image guidance, and permitted edit links', () => {
    fixtures.posts = [ { id: 5, title: { rendered: 'Published update' } } ];
    render( createElement( Edit, { attributes: {}, setAttributes: vi.fn() } ) );
    const card = within( screen.getByRole( 'article' ) );
    expect( card.getByText( 'No featured image' ) ).not.toBeNull();
    expect( card.getByText( 'Article summary.' ) ).not.toBeNull();
    expect( card.getByText( 'September 21, 2026' ) ).not.toBeNull();
    const edit = card.getByRole( 'link', {
      name: 'Edit article: Published update',
    } );
    expect( edit.getAttribute( 'href' ) ).toBe(
      'https://example.test/subsite/wp-admin/post.php?post=5&action=edit'
    );
    expect( edit.getAttribute( 'target' ) ).toBe( '_blank' );
  } );

  it( 'uses the article featured image and its alt text', () => {
    fixtures.posts = [ { id: 5, title: { rendered: 'Photo update' } } ];
    fixtures.image = {
      url: 'https://example.test/uploads/story.jpg',
      alt: 'A CELINA canopy',
    };
    render( createElement( Edit, { attributes: {}, setAttributes: vi.fn() } ) );
    expect(
      screen
        .getByRole( 'img', { name: 'A CELINA canopy' } )
        .getAttribute( 'src' )
    ).toBe( fixtures.image.url );
    expect( screen.queryByText( 'No featured image' ) ).toBeNull();
  } );

  it( 'hides unavailable management permissions without blocking preview', () => {
    fixtures.canManage = false;
    fixtures.posts = [ { id: 5, title: { rendered: 'Published update' } } ];
    render( createElement( Edit, { attributes: {}, setAttributes: vi.fn() } ) );
    expect( screen.queryByRole( 'link' ) ).toBeNull();
    expect(
      screen.getByText( /Your account can preview articles/ )
    ).not.toBeNull();
    expect( screen.getAllByRole( 'article' ) ).toHaveLength( 1 );
  } );

  it( 'shows failures separately from empty results and allows refresh', () => {
    fixtures.requestError = 'Could not load articles. Try Refresh articles.';
    render( createElement( Edit, { attributes: {}, setAttributes: vi.fn() } ) );
    expect( screen.getByRole( 'alert' ).textContent ).toContain(
      'Could not load articles'
    );
    expect( screen.queryByText( /No published articles match/ ) ).toBeNull();
    expect( screen.queryByText( /Loading published posts/ ) ).toBeNull();
    fireEvent.click(
      screen.getByRole( 'button', { name: 'Refresh articles' } )
    );
    expect( fixtures.refresh ).toHaveBeenCalled();
  } );

  it( 'does not report an empty search while the new search text is still pending', () => {
    fixtures.posts = [];
    render(
      createElement( Edit, {
        attributes: { layout: 'featured' },
        setAttributes: vi.fn(),
      } )
    );
    fireEvent.change(
      screen.getByRole( 'textbox', { name: 'Search published articles' } ),
      { target: { value: 'Historical launch' } }
    );
    expect(
      screen.getByText( 'Searching published articles…' )
    ).not.toBeNull();
    expect(
      screen.queryByText( /No published articles match this search/ )
    ).toBeNull();
  } );

  it( 'keeps an older selected story available independently of search results', () => {
    fixtures.searchPostIds = [];
    fixtures.posts = [
      { id: 1001, title: { rendered: 'Historical article' } },
    ];
    render(
      createElement( Edit, {
        attributes: { layout: 'featured', postId: 1001 },
        setAttributes: vi.fn(),
      } )
    );
    expect(
      (
        screen.getByRole( 'combobox', {
          name: 'Featured story',
        } ) as HTMLSelectElement
       ).value
    ).toBe( '1001' );
    expect( screen.queryByRole( 'alert' ) ).toBeNull();
    expect(
      screen.getByRole( 'textbox', { name: 'Search published articles' } )
    ).not.toBeNull();
  } );

  it( 'previews returned posts and stores heading, category, and count changes with their proper types', () => {
    fixtures.categories = [ { id: 42, name: 'Research', parent: 0, count: 3 } ];
    fixtures.posts = [ { id: 5, title: { rendered: 'Published update' } } ];
    const setAttributes = vi.fn();

    render(
      createElement( Edit, {
        attributes: { heading: 'Latest News', category: 0, count: 3 },
        setAttributes,
      } )
    );

    expect(
      screen.getByRole( 'heading', { name: 'Latest News' } )
    ).not.toBeNull();
    expect(
      screen.getByRole( 'heading', { name: 'Published update' } )
    ).not.toBeNull();
    fireEvent.change( screen.getByRole( 'combobox', { name: 'Category' } ), {
      target: { value: '42' },
    } );
    fireEvent.change( screen.getByRole( 'slider', { name: 'Posts' } ), {
      target: { value: '8' },
    } );
    fireEvent.input( screen.getByRole( 'heading', { name: 'Latest News' } ), {
      target: { textContent: 'Editorial news' },
    } );

    expect( setAttributes ).toHaveBeenCalledWith( { category: 42 } );
    expect( setAttributes ).toHaveBeenCalledWith( { count: 8 } );
    expect( setAttributes ).toHaveBeenCalledWith( {
      heading: 'Editorial news',
    } );
  } );

  it( 'renders REST post titles as safe plain text', () => {
    fixtures.posts = [
      {
        id: 5,
        title: {
          rendered: 'Updates &amp; <em>now</em> <img src=x onerror=alert(1)>',
        },
      },
    ];

    render(
      createElement( Edit, {
        attributes: { heading: 'Latest News', category: 0, count: 3 },
        setAttributes: vi.fn(),
      } )
    );

    const title = screen.getByRole( 'heading', { name: 'Updates & now' } );
    expect( title.textContent ).toBe( 'Updates & now ' );
    expect( title.querySelector( 'img' ) ).toBeNull();
  } );
} );
