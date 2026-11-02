import { RichText, useBlockProps } from '@wordpress/block-editor';
import {
  hasCapabilityCardContent,
  type CapabilityCardAttributes,
} from './model';

interface SaveProps {
  attributes: CapabilityCardAttributes;
}

export default function save( { attributes }: SaveProps ) {
  if ( ! hasCapabilityCardContent( attributes ) ) {
    return null;
  }

  return (
    <article
      { ...useBlockProps.save( { className: 'celina-capability-card' } ) }
    >
      <RichText.Content
        tagName="h3"
        className="celina-capability-card__heading"
        value={ attributes.heading }
      />
      { attributes.content && (
        <RichText.Content
          tagName="div"
          className="celina-capability-card__content"
          value={ attributes.content }
        />
      ) }
      { attributes.proof && (
        <RichText.Content
          tagName="p"
          className="celina-capability-card__proof"
          value={ attributes.proof }
        />
      ) }
    </article>
  );
}
