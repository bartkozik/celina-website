import {
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  Spinner,
  ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useId, useState } from 'react';
import {
  useNewsRequest,
  useNewsSettings,
  type NewsPreviewResponse,
} from './editor-data';
import FeaturedPicker from './featured-picker';
import PreviewCard from './preview-card';
import {
  newsCategoryIsWithinBase,
  normalizeNewsAttributes,
  type NewsAttributes,
  type NewsCategoryRecord,
  type NewsLayout,
} from './model';

interface EditProps {
  attributes: Partial< NewsAttributes >;
  setAttributes: ( next: Partial< NewsAttributes > ) => void;
}

interface CoreDataSelectors {
  getResolutionError: ( selector: string, args: unknown[] ) => unknown;
  getEntityRecords: < EntityRecord extends NewsCategoryRecord >(
    kind: 'postType' | 'taxonomy',
    name: 'post' | 'category',
    query: {
      _fields?: string[];
      categories?: number;
      page?: number;
      per_page: number;
      status?: 'publish';
    }
  ) => EntityRecord[] | null;
  getEntityRecordsTotalPages: (
    kind: 'taxonomy',
    name: 'category',
    query: {
      _fields: string[];
      page: number;
      per_page: number;
    }
  ) => number | null;
}

function getCoreDataSelectors(
  select: ( store: string ) => unknown
): CoreDataSelectors {
  return select( 'core' ) as CoreDataSelectors;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const config = normalizeNewsAttributes( attributes );
  const navigationControlId = useId();
  const [ navigationError, setNavigationError ] = useState( '' );
  const [ reorderAnnouncement, setReorderAnnouncement ] = useState( '' );
  const categoryState = useSelect( ( select ) => {
    const coreData = getCoreDataSelectors( select );
    const pageOneQuery = {
      _fields: [ 'id', 'name', 'parent', 'count' ],
      page: 1,
      per_page: 100,
    };
    const pageOne = coreData.getEntityRecords< NewsCategoryRecord >(
      'taxonomy',
      'category',
      pageOneQuery
    );
    const pageOneError = coreData.getResolutionError( 'getEntityRecords', [
      'taxonomy',
      'category',
      pageOneQuery,
    ] );
    if ( pageOne === null || pageOneError ) {
      return { records: null, error: Boolean( pageOneError ) };
    }

    const totalPages = coreData.getEntityRecordsTotalPages(
      'taxonomy',
      'category',
      pageOneQuery
    );
    if ( totalPages === null ) {
      return { records: null, error: false };
    }

    const safeTotalPages =
      Number.isSafeInteger( totalPages ) && totalPages > 1 ? totalPages : 1;
    const resolvedCategories = [ ...pageOne ];
    let allPagesResolved = true;

    for ( let page = 2; page <= safeTotalPages; page += 1 ) {
      const pageRecords = coreData.getEntityRecords< NewsCategoryRecord >(
        'taxonomy',
        'category',
        { ...pageOneQuery, page }
      );
      const pageError = coreData.getResolutionError( 'getEntityRecords', [
        'taxonomy',
        'category',
        { ...pageOneQuery, page },
      ] );
      if ( pageRecords === null || pageError ) {
        if ( pageError ) {
          return { records: null, error: true };
        }
        allPagesResolved = false;
        continue;
      }

      resolvedCategories.push( ...pageRecords );
    }

    return {
      records: allPagesResolved ? resolvedCategories : null,
      error: false,
    };
  }, [] );
  const categories = categoryState.records;
  const settings = useNewsSettings();
  const [ revision, setRevision ] = useState( 0 );
  const previewQuery = new URLSearchParams( {
    layout: config.layout,
    category: String( config.category ),
    count: String( config.count ),
    postId: String( config.postId ),
  } );
  config.navigationCategories.forEach( ( id ) =>
    previewQuery.append( 'navigationCategories[]', String( id ) )
  );
  const preview = useNewsRequest< NewsPreviewResponse >(
    settings,
    previewQuery.toString(),
    revision
  );
  const selectedUnavailable = preview.data?.selectedUnavailable === true;
  const categoriesById = new Map(
    ( categories ?? [] ).map( ( category ) => [ category.id, category ] )
  );
  const invalidNavigationCategories =
    categories === null
      ? []
      : config.navigationCategories.filter( ( categoryId ) => {
          if ( ! categoriesById.has( categoryId ) ) {
            return true;
          }

          return ! newsCategoryIsWithinBase(
            categoryId,
            config.category,
            categories
          );
        } );

  const omittedNavigationCategories = preview.data
    ? config.navigationCategories.filter(
        ( id ) => ! preview.data?.navigationCategories.includes( id )
      )
    : [];

  useEffect( () => {
    setNavigationError( '' );
  }, [ categories, config.category, config.layout ] );

  const removeNavigationCategory = ( categoryId: number ) => {
    setNavigationError( '' );
    setReorderAnnouncement( '' );
    setAttributes( {
      navigationCategories: config.navigationCategories.filter(
        ( savedCategoryId ) => savedCategoryId !== categoryId
      ),
    } );
  };
  const updateNavigationCategory = (
    category: NewsCategoryRecord,
    selected: boolean
  ) => {
    setNavigationError( '' );
    setReorderAnnouncement( '' );

    if ( ! selected ) {
      removeNavigationCategory( category.id );
      return;
    }

    if ( config.navigationCategories.includes( category.id ) ) {
      return;
    }

    if (
      ! newsCategoryIsWithinBase(
        category.id,
        config.category,
        categories ?? []
      )
    ) {
      setNavigationError(
        `${ category.name } is outside the selected base category.`
      );
      return;
    }

    setAttributes( {
      navigationCategories: [ ...config.navigationCategories, category.id ],
    } );
  };
  const moveNavigationCategory = ( index: number, offset: -1 | 1 ) => {
    const targetIndex = index + offset;
    if (
      targetIndex < 0 ||
      targetIndex >= config.navigationCategories.length
    ) {
      return;
    }

    const navigationCategories = [ ...config.navigationCategories ];
    const current = navigationCategories[ index ];
    const target = navigationCategories[ targetIndex ];
    if ( current === undefined || target === undefined ) {
      return;
    }

    navigationCategories[ index ] = target;
    navigationCategories[ targetIndex ] = current;
    setAttributes( { navigationCategories } );
    const categoryName =
      categoriesById.get( current )?.name ?? `Category #${ current }`;
    setReorderAnnouncement(
      `${ categoryName } moved to position ${ targetIndex + 1 } of ${
        navigationCategories.length
      }.`
    );
  };

  return (
    <section { ...useBlockProps( { className: 'celina-news-grid' } ) }>
      <InspectorControls>
        <PanelBody title="News settings">
          <SelectControl
            label="Category"
            value={ String( config.category ) }
            options={ [
              { label: 'All categories', value: '0' },
              ...( config.category > 0 &&
              ! ( categories ?? [] ).some(
                ( item ) => item.id === config.category
              )
                ? [
                    {
                      label: `Selected category #${ config.category }`,
                      value: String( config.category ),
                    },
                  ]
                : [] ),
              ...( categories ?? [] ).map( ( item ) => ( {
                label: item.name,
                value: String( item.id ),
              } ) ),
            ] }
            onChange={ ( value ) =>
              setAttributes( { category: Number( value ) } )
            }
          />
          { categoryState.error && (
            <p role="alert" aria-label="Category loading error">
              Could not load categories. Save your page, then reload the editor
              to retry.
            </p>
          ) }
          { categories === null && ! categoryState.error && (
            <p>Loading categories…</p>
          ) }
          <SelectControl
            label="Layout"
            value={ config.layout }
            options={ [
              { label: 'Grid', value: 'grid' },
              { label: 'Featured story', value: 'featured' },
              { label: 'News index', value: 'news-index' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { layout: value as NewsLayout } )
            }
          />
          { config.layout === 'news-index' && (
            <fieldset className="celina-news-grid__navigation-control">
              <legend>Category navigation</legend>
              <p className="components-base-control__help">
                Select categories, then arrange them in the order they should
                appear.
              </p>
              <p>
                The default category and categories without published articles,
                including articles in descendant categories, are omitted from
                public navigation. Categories must also be within the selected
                base category.
              </p>
              { omittedNavigationCategories.length > 0 && (
                <p>
                  Not shown on the published page:{ ' ' }
                  { omittedNavigationCategories
                    .map(
                      ( id ) =>
                        categoriesById.get( id )?.name ?? `Category #${ id }`
                    )
                    .join( ', ' ) }
                  .
                </p>
              ) }
              { config.navigationCategories.length === 0 && (
                <p>
                  No category navigation selected. Choose categories below to
                  add navigation links.
                </p>
              ) }
              <div className="celina-news-grid__navigation-options">
                { ( categories ?? [] ).map( ( category ) => (
                  <label
                    className="celina-news-grid__navigation-option"
                    htmlFor={ `${ navigationControlId }-${ category.id }` }
                    key={ category.id }
                  >
                    <input
                      checked={ config.navigationCategories.includes(
                        category.id
                      ) }
                      id={ `${ navigationControlId }-${ category.id }` }
                      onChange={ ( event ) =>
                        updateNavigationCategory(
                          category,
                          event.currentTarget.checked
                        )
                      }
                      type="checkbox"
                    />
                    <span>{ category.name }</span>
                    <span aria-hidden="true">({ category.count })</span>
                  </label>
                ) ) }
              </div>
              { config.navigationCategories.length > 0 && (
                <ol className="celina-news-grid__navigation-order">
                  { config.navigationCategories.map( ( categoryId, index ) => {
                    const categoryName =
                      categoriesById.get( categoryId )?.name ??
                      `Category #${ categoryId }`;

                    return (
                      <li key={ categoryId }>
                        <span>{ categoryName }</span>
                        <span className="celina-news-grid__navigation-actions">
                          <button
                            aria-label={ `Move ${ categoryName } up` }
                            disabled={ index === 0 }
                            onClick={ () =>
                              moveNavigationCategory( index, -1 )
                            }
                            type="button"
                          >
                            Move up
                          </button>
                          <button
                            aria-label={ `Move ${ categoryName } down` }
                            disabled={
                              index === config.navigationCategories.length - 1
                            }
                            onClick={ () => moveNavigationCategory( index, 1 ) }
                            type="button"
                          >
                            Move down
                          </button>
                          <button
                            aria-label={ `Remove ${ categoryName } from navigation` }
                            onClick={ () =>
                              removeNavigationCategory( categoryId )
                            }
                            type="button"
                          >
                            Remove
                          </button>
                        </span>
                      </li>
                    );
                  } ) }
                </ol>
              ) }
              { invalidNavigationCategories.length > 0 && (
                <p aria-label="Invalid saved navigation" role="alert">
                  Saved navigation categories need attention:{ ' ' }
                  { invalidNavigationCategories
                    .map(
                      ( categoryId ) =>
                        categoriesById.get( categoryId )?.name ??
                        `Category #${ categoryId }`
                    )
                    .join( ', ' ) }
                  . Remove categories outside the selected base category or no
                  longer available.
                </p>
              ) }
              { navigationError && (
                <p aria-label="Category selection error" role="alert">
                  { navigationError }
                </p>
              ) }
              <p
                aria-label="Category order update"
                aria-live="polite"
                className="screen-reader-text"
                role="status"
              >
                { reorderAnnouncement }
              </p>
            </fieldset>
          ) }
          { config.layout === 'grid' ? (
            <>
              <RangeControl
                label="Posts"
                min={ 1 }
                max={ 12 }
                value={ config.count }
                onChange={ ( value ) => {
                  if ( value !== undefined ) {
                    setAttributes( {
                      count: normalizeNewsAttributes( { count: value } ).count,
                    } );
                  }
                } }
              />
              <ToggleControl
                label="Show pagination"
                checked={ config.pagination }
                onChange={ ( pagination ) => setAttributes( { pagination } ) }
              />
            </>
          ) : (
            <FeaturedPicker
              key={ config.category }
              settings={ settings }
              category={ config.category }
              postId={ config.postId }
              selected={ preview.data?.featured ?? null }
              unavailable={ selectedUnavailable }
              revision={ revision }
              onChange={ ( postId ) => setAttributes( { postId } ) }
            />
          ) }
        </PanelBody>
      </InspectorControls>

      <RichText
        tagName="h2"
        allowedFormats={ [] }
        value={ config.heading }
        onChange={ ( heading: string ) => setAttributes( { heading } ) }
        placeholder="News heading"
      />

      <div className="celina-news-grid__editor-help">
        <p>
          Published articles appear automatically in this block. Set each
          article’s featured image, excerpt, and categories in its article
          editor.
        </p>
        <div className="celina-news-grid__editor-actions">
          { settings?.addUrl && (
            <a
              href={ settings.addUrl }
              target="_blank"
              rel="noopener noreferrer"
            >
              Add article ↗
            </a>
          ) }
          { settings?.manageUrl && (
            <a
              href={ settings.manageUrl }
              target="_blank"
              rel="noopener noreferrer"
            >
              Manage articles ↗
            </a>
          ) }
          <button
            type="button"
            onClick={ () => {
              setRevision( ( current ) => current + 1 );
              preview.refresh();
            } }
          >
            Refresh articles
          </button>
        </div>
        <p>
          Article links open in a new tab, keeping your page edits here. Refresh
          articles after publishing or editing a story.
        </p>
        { settings && ! settings.addUrl && ! settings.manageUrl && (
          <p>
            Your account can preview articles but cannot create or manage them.
          </p>
        ) }
      </div>

      { selectedUnavailable && (
        <p role="alert">
          The selected featured story is unavailable for this category. The
          latest matching published post will be used.
        </p>
      ) }

      { config.layout === 'news-index' && (
        <p className="celina-news-grid__editor-note">
          The published page shows one featured story on page one and a
          deduplicated, paginated list of six latest stories.
        </p>
      ) }

      { preview.error && <p role="alert">{ preview.error }</p> }
      { preview.loading && (
        <div role="status" aria-live="polite">
          <Spinner />
          <span className="screen-reader-text">Loading published posts</span>
        </div>
      ) }
      { preview.data &&
        ! preview.data.featured &&
        preview.data.posts.length === 0 && (
          <div className="celina-news-grid__empty">
            <p>No published articles match this configuration.</p>
            <p>
              Publish an article in the selected category, or choose another
              category. Draft articles do not appear here.
            </p>
          </div>
        ) }
      { preview.data?.featured && (
        <div className="celina-news-grid__featured">
          <PreviewCard post={ preview.data.featured } variant="featured" />
        </div>
      ) }
      { ( preview.data?.posts.length ?? 0 ) > 0 && (
        <div className="celina-news-grid__items">
          { preview.data?.posts.map( ( post ) => (
            <PreviewCard
              post={ post }
              variant={ config.layout }
              key={ post.id }
            />
          ) ) }
        </div>
      ) }
    </section>
  );
}
