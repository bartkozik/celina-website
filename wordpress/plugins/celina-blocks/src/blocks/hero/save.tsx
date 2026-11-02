import { RichText, useBlockProps } from '@wordpress/block-editor';
import { normalizeButtons, type HeroAttributes } from './model';

interface SaveProps {
  attributes: HeroAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const buttons = normalizeButtons( attributes.buttons, attributes.variant );
  const className = `celina-hero celina-hero--${ attributes.variant }`;
  const blockProps = useBlockProps.save( {
    className,
    style: attributes.backgroundUrl
      ? { backgroundImage: `url(${ attributes.backgroundUrl })` }
      : undefined,
  } );

  return (
    <section { ...blockProps }>
      { attributes.backgroundUrl && (
        <div className="celina-hero__overlay" aria-hidden="true" />
      ) }
      <div className="celina-hero__inner">
        { attributes.eyebrow && (
          <RichText.Content
            tagName="p"
            className="celina-hero__eyebrow"
            value={ attributes.eyebrow }
          />
        ) }
        <RichText.Content
          tagName="h1"
          className="celina-hero__heading"
          value={ attributes.heading }
        />
        { attributes.description && (
          <RichText.Content
            tagName="p"
            className="celina-hero__description"
            value={ attributes.description }
          />
        ) }
        { buttons.length > 0 && (
          <div className="celina-hero__actions">
            { buttons.map( ( button ) => (
              <a
                className="celina-button"
                href={ button.url }
                key={ `${ button.label }:${ button.url }` }
              >
                { button.label }
              </a>
            ) ) }
          </div>
        ) }
      </div>
    </section>
  );
}
