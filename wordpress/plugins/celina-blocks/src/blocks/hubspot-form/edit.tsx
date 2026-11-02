import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import {
  isHubSpotConfigurationValid,
  type HubSpotAttributes,
  type HubSpotRegion,
} from './model';

interface EditProps {
  attributes: HubSpotAttributes;
  setAttributes: ( next: Partial< HubSpotAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const configured = isHubSpotConfigurationValid( attributes );

  return (
    <section
      { ...useBlockProps( {
        className: `celina-hubspot-form${
          configured ? ' celina-hubspot-form--configured' : ''
        }`,
      } ) }
    >
      <InspectorControls>
        <PanelBody title="HubSpot settings">
          <TextControl
            label="Heading"
            value={ attributes.heading }
            onChange={ ( heading ) => setAttributes( { heading } ) }
          />
          <SelectControl
            label="Region"
            value={ attributes.region }
            options={ [
              { label: 'North America (na1)', value: 'na1' },
              { label: 'European Union (eu1)', value: 'eu1' },
            ] }
            onChange={ ( region ) =>
              setAttributes( { region: region as HubSpotRegion } )
            }
          />
          <TextControl
            label="Portal ID"
            value={ attributes.portalId }
            onChange={ ( portalId ) => setAttributes( { portalId } ) }
          />
          <TextControl
            label="Form ID"
            value={ attributes.formId }
            onChange={ ( formId ) => setAttributes( { formId } ) }
          />
          <TextControl
            label="Fallback contact text"
            value={ attributes.fallbackText }
            onChange={ ( fallbackText ) => setAttributes( { fallbackText } ) }
          />
        </PanelBody>
      </InspectorControls>

      { attributes.heading && <h2>{ attributes.heading }</h2> }
      <div className="celina-hubspot-form__status" role="status">
        <p>
          { configured
            ? 'HubSpot form is configured. It loads only on the frontend.'
            : 'HubSpot form is not configured in this workbench.' }
        </p>
        { attributes.fallbackText && <p>{ attributes.fallbackText }</p> }
      </div>
    </section>
  );
}
