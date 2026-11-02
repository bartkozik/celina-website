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
  ALLOWED_RESOURCE_BLOCKS,
  RESOURCE_TEMPLATE,
  normalizeResourceVariant,
  type ResourceListAttributes,
  type ResourceVariant,
} from './model';

interface EditProps {
  attributes: ResourceListAttributes;
  setAttributes: ( next: Partial< ResourceListAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const variant = normalizeResourceVariant( attributes.variant );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Section settings">
          <SelectControl
            label="Presentation"
            value={ variant }
            options={ [
              { label: 'Standard', value: 'standard' },
              { label: 'Featured', value: 'featured' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { variant: value as ResourceVariant } )
            }
          />
        </PanelBody>
      </InspectorControls>

      <section
        { ...useBlockProps( {
          className: `celina-resource-list celina-resource-list--${ variant } celina-collection-editor`,
        } ) }
      >
        <div className="celina-resource-list__inner">
          <header className="celina-resource-list__header">
            <RichText
              tagName="p"
              className="celina-resource-list__eyebrow"
              value={ attributes.eyebrow }
              onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
              placeholder="Eyebrow (optional)"
            />
            <RichText
              tagName="h2"
              className="celina-resource-list__heading"
              value={ attributes.heading }
              onChange={ ( heading: string ) => setAttributes( { heading } ) }
              placeholder="Resources heading"
            />
            <RichContentEditor
              className="celina-resource-list__introduction"
              value={ attributes.introduction }
              onChange={ ( introduction: string ) =>
                setAttributes( { introduction } )
              }
              placeholder="Section introduction (optional)"
            />
          </header>
          <ul className="celina-resource-list__items">
            <InnerBlocks
              allowedBlocks={ ALLOWED_RESOURCE_BLOCKS }
              template={ RESOURCE_TEMPLATE }
              renderAppender={ InnerBlocks.ButtonBlockAppender }
            />
          </ul>
        </div>
      </section>
    </>
  );
}
