import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
import {
  normalizeIndustryColumns,
  type IndustryCollectionAttributes,
} from './model';

interface SaveProps {
  attributes: IndustryCollectionAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const columns = normalizeIndustryColumns( attributes.columns );

  return (
    <section
      { ...useBlockProps.save( {
        className: `celina-industry-cards celina-industry-cards--columns-${ columns }`,
      } ) }
    >
      <div className="celina-industry-cards__inner">
        <header className="celina-industry-cards__header">
          { attributes.eyebrow && (
            <RichText.Content
              tagName="p"
              className="celina-industry-cards__eyebrow"
              value={ attributes.eyebrow }
            />
          ) }
          <RichText.Content
            tagName="h2"
            className="celina-industry-cards__heading"
            value={ attributes.heading }
          />
          { attributes.introduction && (
            <RichText.Content
              tagName="div"
              className="celina-industry-cards__introduction"
              value={ attributes.introduction }
            />
          ) }
        </header>
        <div className="celina-industry-cards__grid">
          <InnerBlocks.Content />
        </div>
      </div>
    </section>
  );
}
