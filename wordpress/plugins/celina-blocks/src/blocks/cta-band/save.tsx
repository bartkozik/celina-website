import { RichText, useBlockProps } from '@wordpress/block-editor';
import { getRenderableCtaLinks, type CtaBandAttributes } from './model';

interface SaveProps {
  attributes: CtaBandAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const links = getRenderableCtaLinks( attributes );

  return (
    <section { ...useBlockProps.save( { className: 'celina-cta-band' } ) }>
      <div className="celina-cta-band__inner">
        { attributes.eyebrow && (
          <RichText.Content
            tagName="p"
            className="celina-cta-band__eyebrow"
            value={ attributes.eyebrow }
          />
        ) }
        <RichText.Content
          tagName="h2"
          className="celina-cta-band__heading"
          value={ attributes.heading }
        />
        { attributes.description && (
          <RichText.Content
            tagName="p"
            className="celina-cta-band__description"
            value={ attributes.description }
          />
        ) }
        { links.length > 0 && (
          <div className="celina-cta-band__actions">
            { links.map( ( link ) => (
              <a
                className={ `celina-button celina-button--${ link.variant }` }
                href={ link.url }
                key={ link.variant }
                target={ link.newTab ? '_blank' : undefined }
                rel={ link.newTab ? 'noopener noreferrer' : undefined }
              >
                { link.label }
              </a>
            ) ) }
          </div>
        ) }
      </div>
    </section>
  );
}
