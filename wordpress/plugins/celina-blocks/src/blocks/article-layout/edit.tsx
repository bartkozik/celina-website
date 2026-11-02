import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { ALLOWED_ARTICLE_BLOCKS, ARTICLE_TEMPLATE } from './model';

export default function Edit() {
  return (
    <div { ...useBlockProps( { className: 'celina-article-layout' } ) }>
      <InnerBlocks
        allowedBlocks={ ALLOWED_ARTICLE_BLOCKS }
        template={ ARTICLE_TEMPLATE }
        templateLock={ false }
        renderAppender={ InnerBlocks.ButtonBlockAppender }
      />
    </div>
  );
}
