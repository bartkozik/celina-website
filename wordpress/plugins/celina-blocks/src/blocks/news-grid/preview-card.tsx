import { newsPlainText, type NewsEditorPost } from './editor-data';
import type { NewsLayout } from './model';

export default function PreviewCard( {
  post,
  variant,
}: {
  post: NewsEditorPost;
  variant: NewsLayout;
} ) {
  const title = newsPlainText( post.title ) || 'Untitled article';
  return (
    <article className={ `celina-news-card celina-news-card--${ variant }` }>
      <div className="celina-news-card__media">
        { post.image ? (
          <img src={ post.image.url } alt={ post.image.alt } />
        ) : (
          <p className="celina-news-card__missing-image">No featured image</p>
        ) }
      </div>
      <div className="celina-news-card__content">
        { post.categories.length > 0 && (
          <span className="celina-news-card__category">
            { post.categories.map( newsPlainText ).join( ', ' ) }
          </span>
        ) }
        <h3>{ title }</h3>
        { variant !== 'news-index' && post.excerpt && (
          <p>{ newsPlainText( post.excerpt ) }</p>
        ) }
        <time dateTime={ post.dateTime }>{ post.date }</time>
        { post.editUrl && (
          <a
            aria-label={ `Edit article: ${ title }` }
            className="celina-news-card__edit"
            href={ post.editUrl }
            rel="noopener noreferrer"
            target="_blank"
          >
            Edit article ↗
          </a>
        ) }
      </div>
    </article>
  );
}
