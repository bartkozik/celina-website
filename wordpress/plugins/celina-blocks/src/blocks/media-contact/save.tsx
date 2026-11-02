import { RichText, useBlockProps } from '@wordpress/block-editor';
import {
  getPhoneHref,
  normalizeEmail,
  type MediaContactAttributes,
} from './model';

interface SaveProps {
  attributes: MediaContactAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const email = normalizeEmail( attributes.email );
  const phoneHref = getPhoneHref( attributes.phone );

  return (
    <aside { ...useBlockProps.save( { className: 'celina-media-contact' } ) }>
      { attributes.eyebrow && (
        <RichText.Content
          tagName="p"
          className="celina-media-contact__eyebrow"
          value={ attributes.eyebrow }
        />
      ) }
      { attributes.heading && (
        <RichText.Content
          tagName="h2"
          className="celina-media-contact__heading"
          value={ attributes.heading }
        />
      ) }
      <address className="celina-media-contact__details">
        { attributes.name && (
          <RichText.Content
            tagName="strong"
            className="celina-media-contact__name"
            value={ attributes.name }
          />
        ) }
        { attributes.role && (
          <RichText.Content
            tagName="span"
            className="celina-media-contact__role"
            value={ attributes.role }
          />
        ) }
        { email && <a href={ `mailto:${ email }` }>{ email }</a> }
        { phoneHref && <a href={ phoneHref }>{ attributes.phone.trim() }</a> }
      </address>
    </aside>
  );
}
