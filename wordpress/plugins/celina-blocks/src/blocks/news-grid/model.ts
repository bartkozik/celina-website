export type NewsLayout = 'grid' | 'featured' | 'news-index';

export interface NewsAttributes extends Record< string, unknown > {
  heading: string;
  category: number;
  count: number;
  layout: NewsLayout;
  postId: number;
  pagination: boolean;
  navigationCategories: number[];
}

export interface NewsPostRecord {
  id: number;
  title: { rendered: string };
}

export interface NewsCategoryRecord {
  id: number;
  name: string;
  parent: number;
  count: number;
}

export const defaultNewsAttributes: NewsAttributes = {
  heading: 'Latest News',
  category: 0,
  count: 3,
  layout: 'grid',
  postId: 0,
  pagination: false,
  navigationCategories: [],
};

export function clampNewsCount( value: unknown ): number {
  const count =
    typeof value === 'number' && Number.isFinite( value )
      ? Math.trunc( value )
      : defaultNewsAttributes.count;

  return Math.max( 1, Math.min( 12, count ) );
}

export function normalizeNewsId( value: unknown ): number {
  return typeof value === 'number' && Number.isFinite( value )
    ? Math.max( 0, Math.trunc( value ) )
    : 0;
}

export function normalizeNewsCategoryIds( value: unknown ): number[] {
  if ( ! Array.isArray( value ) ) {
    return [];
  }

  return Array.from(
    new Set(
      value
        .filter(
          ( item ): item is number =>
            typeof item === 'number' && Number.isFinite( item )
        )
        .map( ( item ) => Math.trunc( item ) )
        .filter( ( item ) => item > 0 )
    )
  );
}

export function normalizeNewsLayout( value: unknown ): NewsLayout {
  return value === 'featured' || value === 'news-index' ? value : 'grid';
}

export function newsCategoryIsWithinBase(
  categoryId: number,
  baseCategoryId: number,
  categories: NewsCategoryRecord[]
): boolean {
  if ( baseCategoryId === 0 ) {
    return true;
  }

  const categoriesById = new Map(
    categories.map( ( category ) => [ category.id, category ] )
  );
  const visited = new Set< number >();
  let currentCategoryId = categoryId;

  while ( currentCategoryId > 0 && ! visited.has( currentCategoryId ) ) {
    if ( currentCategoryId === baseCategoryId ) {
      return true;
    }

    visited.add( currentCategoryId );
    const currentCategory = categoriesById.get( currentCategoryId );
    if ( ! currentCategory ) {
      return false;
    }

    currentCategoryId = currentCategory.parent;
  }

  return false;
}

export function normalizeNewsAttributes(
  attributes: Partial< NewsAttributes >
): NewsAttributes {
  const layout = normalizeNewsLayout( attributes.layout );

  return {
    heading:
      typeof attributes.heading === 'string'
        ? attributes.heading
        : defaultNewsAttributes.heading,
    category: normalizeNewsId( attributes.category ),
    count: clampNewsCount( attributes.count ),
    layout,
    postId: normalizeNewsId( attributes.postId ),
    pagination: layout === 'grid' && attributes.pagination === true,
    navigationCategories: normalizeNewsCategoryIds(
      attributes.navigationCategories
    ),
  };
}

export function selectedPostIsUnavailable(
  postId: number,
  posts: NewsPostRecord[] | null
): boolean {
  return (
    postId > 0 &&
    Array.isArray( posts ) &&
    ! posts.some( ( post ) => post.id === postId )
  );
}
