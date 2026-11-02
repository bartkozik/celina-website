import RichContentEditor from '../../shared/rich-content-editor';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import type { CapabilityCardAttributes } from './model';

interface EditProps {
  attributes: CapabilityCardAttributes;
  setAttributes: ( next: Partial< CapabilityCardAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  return (
    <article { ...useBlockProps( { className: 'celina-capability-card' } ) }>
      <RichText
        tagName="h3"
        className="celina-capability-card__heading"
        value={ attributes.heading }
        onChange={ ( heading: string ) => setAttributes( { heading } ) }
        placeholder="Capability"
      />
      <RichContentEditor
        className="celina-capability-card__content"
        value={ attributes.content }
        onChange={ ( content: string ) => setAttributes( { content } ) }
        placeholder="Capability description"
      />
      <RichText
        tagName="p"
        className="celina-capability-card__proof"
        value={ attributes.proof }
        onChange={ ( proof: string ) => setAttributes( { proof } ) }
        placeholder="Proof point (optional)"
      />
    </article>
  );
}
