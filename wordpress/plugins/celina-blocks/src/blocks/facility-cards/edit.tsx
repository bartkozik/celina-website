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
  ALLOWED_FACILITY_BLOCKS,
  FACILITY_TEMPLATE,
  normalizeFacilityColumns,
  type FacilityCollectionAttributes,
  type FacilityColumns,
} from './model';

interface EditProps {
  attributes: FacilityCollectionAttributes;
  setAttributes: ( next: Partial< FacilityCollectionAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const columns = normalizeFacilityColumns( attributes.columns );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Section settings">
          <SelectControl
            label="Columns"
            value={ String( columns ) }
            options={ [ 2, 3 ].map( ( value ) => ( {
              label: String( value ),
              value: String( value ),
            } ) ) }
            onChange={ ( value ) =>
              setAttributes( {
                columns: Number( value ) as FacilityColumns,
              } )
            }
          />
        </PanelBody>
      </InspectorControls>

      <section
        { ...useBlockProps( {
          className: `celina-facility-cards celina-facility-cards--columns-${ columns } celina-collection-editor`,
        } ) }
      >
        <div className="celina-facility-cards__inner">
          <header className="celina-facility-cards__header">
            <RichText
              tagName="p"
              className="celina-facility-cards__eyebrow"
              value={ attributes.eyebrow }
              onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
              placeholder="Eyebrow (optional)"
            />
            <RichText
              tagName="h2"
              className="celina-facility-cards__heading"
              value={ attributes.heading }
              onChange={ ( heading: string ) => setAttributes( { heading } ) }
              placeholder="Facilities heading"
            />
            <RichContentEditor
              className="celina-facility-cards__introduction"
              value={ attributes.introduction }
              onChange={ ( introduction: string ) =>
                setAttributes( { introduction } )
              }
              placeholder="Section introduction (optional)"
            />
          </header>
          <div className="celina-facility-cards__grid">
            <InnerBlocks
              allowedBlocks={ ALLOWED_FACILITY_BLOCKS }
              template={ FACILITY_TEMPLATE }
              renderAppender={ InnerBlocks.ButtonBlockAppender }
            />
          </div>
        </div>
      </section>
    </>
  );
}
