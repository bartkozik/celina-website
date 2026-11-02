import RichContentEditor from '../../shared/rich-content-editor';
import {
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import {
  normalizeVariant,
  type IntroBandAttributes,
  type IntroBandVariant,
} from './model';

interface EditProps {
  attributes: IntroBandAttributes;
  setAttributes: ( next: Partial< IntroBandAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const variant = normalizeVariant( attributes.variant );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Section settings">
          <SelectControl
            label="Background"
            value={ variant }
            options={ [
              { label: 'Neutral', value: 'neutral' },
              { label: 'Warm', value: 'warm' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { variant: value as IntroBandVariant } )
            }
          />
        </PanelBody>
      </InspectorControls>

      <section
        { ...useBlockProps( {
          className: `celina-intro-band celina-intro-band--${ variant }`,
        } ) }
      >
        <RichText
          tagName="p"
          className="celina-intro-band__eyebrow"
          value={ attributes.eyebrow }
          onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
          placeholder="Eyebrow (optional)"
        />
        <RichText
          tagName="h2"
          className="celina-intro-band__heading"
          value={ attributes.heading }
          onChange={ ( heading: string ) => setAttributes( { heading } ) }
          placeholder="Heading (optional)"
        />
        <RichContentEditor
          className="celina-intro-band__content"
          value={ attributes.content }
          onChange={ ( content: string ) => setAttributes( { content } ) }
          placeholder="Introduction"
        />
      </section>
    </>
  );
}
