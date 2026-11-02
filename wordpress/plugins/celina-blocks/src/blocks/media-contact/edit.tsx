import { RichText, useBlockProps } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import type { MediaContactAttributes } from './model';

interface EditProps {
  attributes: MediaContactAttributes;
  setAttributes: ( next: Partial< MediaContactAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  return (
    <aside { ...useBlockProps( { className: 'celina-media-contact' } ) }>
      <RichText
        tagName="p"
        className="celina-media-contact__eyebrow"
        value={ attributes.eyebrow }
        onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
        placeholder="Eyebrow (optional)"
      />
      <RichText
        tagName="h2"
        className="celina-media-contact__heading"
        value={ attributes.heading }
        onChange={ ( heading: string ) => setAttributes( { heading } ) }
        placeholder="Media Contact"
      />
      <RichText
        tagName="p"
        className="celina-media-contact__name"
        value={ attributes.name }
        onChange={ ( name: string ) => setAttributes( { name } ) }
        placeholder="Contact name"
      />
      <RichText
        tagName="p"
        className="celina-media-contact__role"
        value={ attributes.role }
        onChange={ ( role: string ) => setAttributes( { role } ) }
        placeholder="Role (optional)"
      />
      <TextControl
        label="Email"
        value={ attributes.email }
        onChange={ ( email ) => setAttributes( { email } ) }
      />
      <TextControl
        label="Phone"
        value={ attributes.phone }
        onChange={ ( phone ) => setAttributes( { phone } ) }
      />
    </aside>
  );
}
