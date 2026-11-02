import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
import {
  normalizeCapabilityColumns,
  normalizeCapabilityVariant,
  type CapabilityCollectionAttributes,
} from './model';

interface SaveProps {
  attributes: CapabilityCollectionAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const variant = normalizeCapabilityVariant( attributes.variant );
  const columns = normalizeCapabilityColumns( attributes.columns );

  return (
    <section
      { ...useBlockProps.save( {
        className: [
          'celina-capability-cards',
          `celina-capability-cards--${ variant }`,
          `celina-capability-cards--columns-${ columns }`,
        ].join( ' ' ),
      } ) }
    >
      <div className="celina-capability-cards__inner">
        <header className="celina-capability-cards__header">
          { attributes.eyebrow && (
            <RichText.Content
              tagName="p"
              className="celina-capability-cards__eyebrow"
              value={ attributes.eyebrow }
            />
          ) }
          <RichText.Content
            tagName="h2"
            className="celina-capability-cards__heading"
            value={ attributes.heading }
          />
          { attributes.introduction && (
            <RichText.Content
              tagName="div"
              className="celina-capability-cards__introduction"
              value={ attributes.introduction }
            />
          ) }
        </header>
        <div className="celina-capability-cards__grid">
          <InnerBlocks.Content />
        </div>
      </div>
    </section>
  );
}
