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
  ALLOWED_INDUSTRY_BLOCKS,
  INDUSTRY_TEMPLATE,
  normalizeIndustryColumns,
  type IndustryCollectionAttributes,
  type IndustryColumns,
} from './model';

interface EditProps {
  attributes: IndustryCollectionAttributes;
  setAttributes: ( next: Partial< IndustryCollectionAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const columns = normalizeIndustryColumns( attributes.columns );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Section settings">
          <SelectControl
            label="Columns"
            value={ String( columns ) }
            options={ [ 1, 2, 3, 4 ].map( ( value ) => ( {
              label: String( value ),
              value: String( value ),
            } ) ) }
            onChange={ ( value ) =>
              setAttributes( { columns: Number( value ) as IndustryColumns } )
            }
          />
        </PanelBody>
      </InspectorControls>

      <section
        { ...useBlockProps( {
          className: `celina-industry-cards celina-industry-cards--columns-${ columns } celina-collection-editor`,
        } ) }
      >
        <div className="celina-industry-cards__inner">
          <header className="celina-industry-cards__header">
            <RichText
              tagName="p"
              className="celina-industry-cards__eyebrow"
              value={ attributes.eyebrow }
              onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
              placeholder="Eyebrow (optional)"
            />
            <RichText
              tagName="h2"
              className="celina-industry-cards__heading"
              value={ attributes.heading }
              onChange={ ( heading: string ) => setAttributes( { heading } ) }
              placeholder="Industries heading"
            />
            <RichContentEditor
              className="celina-industry-cards__introduction"
              value={ attributes.introduction }
              onChange={ ( introduction: string ) =>
                setAttributes( { introduction } )
              }
              placeholder="Section introduction (optional)"
            />
          </header>
          <div className="celina-industry-cards__grid">
            <InnerBlocks
              allowedBlocks={ ALLOWED_INDUSTRY_BLOCKS }
              template={ INDUSTRY_TEMPLATE }
              renderAppender={ InnerBlocks.ButtonBlockAppender }
            />
          </div>
        </div>
      </section>
    </>
  );
}
