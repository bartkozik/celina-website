import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import type { BambooHrAttributes } from './model';

interface EditProps {
  attributes: BambooHrAttributes;
  setAttributes: ( next: Partial< BambooHrAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  return (
    <section { ...useBlockProps( { className: 'celina-bamboohr-jobs' } ) }>
      <InspectorControls>
        <PanelBody title="Legacy BambooHR Jobs">
          <p>Replace this legacy block with Paycor Jobs.</p>
          <TextControl
            label="Heading"
            value={ attributes.heading }
            onChange={ ( heading ) => setAttributes( { heading } ) }
          />
          <TextControl
            label="Fallback text"
            value={ attributes.fallbackText }
            onChange={ ( fallbackText ) => setAttributes( { fallbackText } ) }
          />
        </PanelBody>
      </InspectorControls>

      <h2>Legacy BambooHR Jobs</h2>
      <div className="celina-bamboohr-jobs__status" role="status">
        <p>Replace this legacy block with Paycor Jobs.</p>
      </div>
    </section>
  );
}
