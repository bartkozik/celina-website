import RichContentEditor from '../../shared/rich-content-editor';
import '../../shared/collection-editor.scss';
import {
  InnerBlocks,
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import {
  ALLOWED_CAPABILITY_BLOCKS,
  CAPABILITY_TEMPLATE,
  normalizeCapabilityColumns,
  normalizeCapabilityVariant,
  type CapabilityCollectionAttributes,
  type CapabilityColumns,
  type CapabilityVariant,
} from './model';

interface EditProps {
  attributes: CapabilityCollectionAttributes;
  setAttributes: ( next: Partial< CapabilityCollectionAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const variant = normalizeCapabilityVariant( attributes.variant );
  const columns = normalizeCapabilityColumns( attributes.columns );
  const className = [
    'celina-capability-cards',
    `celina-capability-cards--${ variant }`,
    `celina-capability-cards--columns-${ columns }`,
  ].join( ' ' );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Section settings">
          <SelectControl
            label="Color variant"
            value={ variant }
            options={ [
              { label: 'Light', value: 'light' },
              { label: 'Dark', value: 'dark' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { variant: value as CapabilityVariant } )
            }
          />
          <SelectControl
            label="Columns"
            value={ String( columns ) }
            options={ [ 2, 3, 4 ].map( ( value ) => ( {
              label: String( value ),
              value: String( value ),
            } ) ) }
            onChange={ ( value ) =>
              setAttributes( {
                columns: Number( value ) as CapabilityColumns,
              } )
            }
          />
        </PanelBody>
      </InspectorControls>

      <section
        { ...useBlockProps( {
          className: `${ className } celina-collection-editor`,
        } ) }
      >
        <div className="celina-capability-cards__inner">
          <header className="celina-capability-cards__header">
            <RichText
              tagName="p"
              className="celina-capability-cards__eyebrow"
              value={ attributes.eyebrow }
              onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
              placeholder="Eyebrow (optional)"
            />
            <RichText
              tagName="h2"
              className="celina-capability-cards__heading"
              value={ attributes.heading }
              onChange={ ( heading: string ) => setAttributes( { heading } ) }
              placeholder="Capabilities heading"
            />
            <RichContentEditor
              className="celina-capability-cards__introduction"
              value={ attributes.introduction }
              onChange={ ( introduction: string ) =>
                setAttributes( { introduction } )
              }
              placeholder="Section introduction (optional)"
            />
          </header>
          <div className="celina-capability-cards__grid">
            <InnerBlocks
              allowedBlocks={ ALLOWED_CAPABILITY_BLOCKS }
              template={ CAPABILITY_TEMPLATE }
              renderAppender={ InnerBlocks.ButtonBlockAppender }
            />
          </div>
        </div>
      </section>
    </>
  );
}
