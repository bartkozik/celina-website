import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
import {
  normalizeFacilityColumns,
  type FacilityCollectionAttributes,
} from './model';

interface SaveProps {
  attributes: FacilityCollectionAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const columns = normalizeFacilityColumns( attributes.columns );

  return (
    <section
      { ...useBlockProps.save( {
        className: `celina-facility-cards celina-facility-cards--columns-${ columns }`,
      } ) }
    >
      <div className="celina-facility-cards__inner">
        <header className="celina-facility-cards__header">
          { attributes.eyebrow && (
            <RichText.Content
              tagName="p"
              className="celina-facility-cards__eyebrow"
              value={ attributes.eyebrow }
            />
          ) }
          <RichText.Content
            tagName="h2"
            className="celina-facility-cards__heading"
            value={ attributes.heading }
          />
          { attributes.introduction && (
            <RichText.Content
              tagName="div"
              className="celina-facility-cards__introduction"
              value={ attributes.introduction }
            />
          ) }
        </header>
        <div className="celina-facility-cards__grid">
          <InnerBlocks.Content />
        </div>
      </div>
    </section>
  );
}
