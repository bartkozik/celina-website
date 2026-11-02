import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { isPaycorClientIdValid, type PaycorAttributes } from './model';

interface EditProps {
  attributes: PaycorAttributes;
  setAttributes: ( next: Partial< PaycorAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const configured = isPaycorClientIdValid( attributes.clientId );

  return (
    <section
      { ...useBlockProps( {
        className: `celina-paycor-jobs${
          configured ? ' celina-paycor-jobs--configured' : ''
        }`,
      } ) }
    >
      <InspectorControls>
        <PanelBody title="Paycor settings">
          <TextControl
            label="Heading"
            value={ attributes.heading }
            onChange={ ( heading ) => setAttributes( { heading } ) }
          />
          <TextControl
            label="Paycor client ID"
            value={ attributes.clientId }
            onChange={ ( clientId ) => setAttributes( { clientId } ) }
          />
          <TextControl
            label="Fallback text"
            value={ attributes.fallbackText }
            onChange={ ( fallbackText ) => setAttributes( { fallbackText } ) }
          />
          <TextControl
            label="Direct jobs link text"
            value={ attributes.jobsLinkText }
            onChange={ ( jobsLinkText ) => setAttributes( { jobsLinkText } ) }
          />
        </PanelBody>
      </InspectorControls>

      { attributes.heading && <h2>{ attributes.heading }</h2> }
      <div className="celina-paycor-jobs__status" role="status">
        <p>
          { configured
            ? 'Paycor jobs are configured. The live list loads only on the frontend.'
            : 'Paycor jobs are not configured in this workbench.' }
        </p>
        { attributes.fallbackText && <p>{ attributes.fallbackText }</p> }
      </div>
    </section>
  );
}
