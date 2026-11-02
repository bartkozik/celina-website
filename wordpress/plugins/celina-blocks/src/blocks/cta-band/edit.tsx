import {
  InspectorControls,
  RichText,
  URLInputButton,
  useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import type { CtaBandAttributes } from './model';
import LinkEditorNotice from '../../shared/link-editor-notice';

interface EditProps {
  attributes: CtaBandAttributes;
  setAttributes: ( next: Partial< CtaBandAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  return (
    <>
      <InspectorControls>
        <PanelBody title="Link settings">
          <ToggleControl
            label="Open primary link in a new tab"
            checked={ attributes.primaryNewTab }
            onChange={ ( primaryNewTab ) => setAttributes( { primaryNewTab } ) }
          />
          <ToggleControl
            label="Open secondary link in a new tab"
            checked={ attributes.secondaryNewTab }
            onChange={ ( secondaryNewTab ) =>
              setAttributes( { secondaryNewTab } )
            }
          />
        </PanelBody>
      </InspectorControls>

      <section { ...useBlockProps( { className: 'celina-cta-band' } ) }>
        <div className="celina-cta-band__inner">
          <RichText
            tagName="p"
            className="celina-cta-band__eyebrow"
            value={ attributes.eyebrow }
            onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
            placeholder="Eyebrow (optional)"
          />
          <RichText
            tagName="h2"
            className="celina-cta-band__heading"
            value={ attributes.heading }
            onChange={ ( heading: string ) => setAttributes( { heading } ) }
            placeholder="Call-to-action heading"
          />
          <RichText
            tagName="p"
            className="celina-cta-band__description"
            value={ attributes.description }
            onChange={ ( description: string ) =>
              setAttributes( { description } )
            }
            placeholder="Description (optional)"
          />

          <div className="celina-cta-band__actions">
            <div className="celina-cta-band__action-editor">
              <RichText
                tagName="span"
                value={ attributes.primaryLabel }
                onChange={ ( primaryLabel: string ) =>
                  setAttributes( { primaryLabel } )
                }
                placeholder="Primary button label"
                allowedFormats={ [] }
              />
              <URLInputButton
                url={ attributes.primaryUrl }
                onChange={ ( primaryUrl: string ) =>
                  setAttributes( { primaryUrl } )
                }
              />
            </div>
            <div className="celina-cta-band__action-editor">
              <RichText
                tagName="span"
                value={ attributes.secondaryLabel }
                onChange={ ( secondaryLabel: string ) =>
                  setAttributes( { secondaryLabel } )
                }
                placeholder="Secondary button label"
                allowedFormats={ [] }
              />
              <URLInputButton
                url={ attributes.secondaryUrl }
                onChange={ ( secondaryUrl: string ) =>
                  setAttributes( { secondaryUrl } )
                }
              />
            </div>
          </div>
          <LinkEditorNotice
            label={ attributes.primaryLabel }
            url={ attributes.primaryUrl }
          />
          <LinkEditorNotice
            label={ attributes.secondaryLabel }
            url={ attributes.secondaryUrl }
          />
        </div>
      </section>
    </>
  );
}
