import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
import type { FaqAttributes } from './model';

interface SaveProps {
  attributes: FaqAttributes;
}

export default function save( { attributes }: SaveProps ) {
  return (
    <section { ...useBlockProps.save( { className: 'celina-faq' } ) }>
      { ( attributes.eyebrow || attributes.heading ) && (
        <header className="celina-faq__header">
          { attributes.eyebrow && (
            <RichText.Content
              tagName="p"
              className="celina-faq__eyebrow"
              value={ attributes.eyebrow }
            />
          ) }
          { attributes.heading && (
            <RichText.Content
              tagName="h2"
              className="celina-faq__heading"
              value={ attributes.heading }
            />
          ) }
        </header>
      ) }
      <InnerBlocks.Content />
    </section>
  );
}
