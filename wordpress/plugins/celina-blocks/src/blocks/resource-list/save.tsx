import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
import { normalizeResourceVariant, type ResourceListAttributes } from './model';

interface SaveProps {
  attributes: ResourceListAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const variant = normalizeResourceVariant( attributes.variant );

  return (
    <section
      { ...useBlockProps.save( {
        className: `celina-resource-list celina-resource-list--${ variant }`,
      } ) }
    >
      <div className="celina-resource-list__inner">
        <header className="celina-resource-list__header">
          { attributes.eyebrow && (
            <RichText.Content
              tagName="p"
              className="celina-resource-list__eyebrow"
              value={ attributes.eyebrow }
            />
          ) }
          <RichText.Content
            tagName="h2"
            className="celina-resource-list__heading"
            value={ attributes.heading }
          />
          { attributes.introduction && (
            <RichText.Content
              tagName="div"
              className="celina-resource-list__introduction"
              value={ attributes.introduction }
            />
          ) }
        </header>
        <ul className="celina-resource-list__items">
          <InnerBlocks.Content />
        </ul>
      </div>
    </section>
  );
}
