import { RichText, useBlockProps } from '@wordpress/block-editor';
import { normalizeVariant, type IntroBandAttributes } from './model';

interface SaveProps {
  attributes: IntroBandAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const className = `celina-intro-band celina-intro-band--${ normalizeVariant(
    attributes.variant
  ) }`;

  return (
    <section { ...useBlockProps.save( { className } ) }>
      { attributes.eyebrow && (
        <RichText.Content
          tagName="p"
          className="celina-intro-band__eyebrow"
          value={ attributes.eyebrow }
        />
      ) }
      { attributes.heading && (
        <RichText.Content
          tagName="h2"
          className="celina-intro-band__heading"
          value={ attributes.heading }
        />
      ) }
      <RichText.Content
        tagName="div"
        className="celina-intro-band__content"
        value={ attributes.content }
      />
    </section>
  );
}
