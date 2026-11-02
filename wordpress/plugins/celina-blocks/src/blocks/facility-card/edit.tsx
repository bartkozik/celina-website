import RichContentEditor from '../../shared/rich-content-editor';
import {
  InspectorControls,
  MediaUpload,
  MediaUploadCheck,
  RichText,
  URLInputButton,
  useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody, TextControl } from '@wordpress/components';
import {
  normalizeFacilityCardAttributes,
  type FacilityCardAttributes,
} from './model';
import LinkEditorNotice from '../../shared/link-editor-notice';

interface EditProps {
  attributes: FacilityCardAttributes;
  setAttributes: ( next: Partial< FacilityCardAttributes > ) => void;
}

interface SelectedImage {
  id?: number;
  url?: string;
  alt?: string;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const normalized = normalizeFacilityCardAttributes( attributes );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Facility settings">
          <TextControl
            label="Image alternative text"
            value={ normalized.imageAlt }
            onChange={ ( imageAlt ) => setAttributes( { imageAlt } ) }
          />
          <TextControl
            label="Phone"
            value={ normalized.phone }
            onChange={ ( phone ) => setAttributes( { phone } ) }
          />
          <TextControl
            label="Email"
            value={ normalized.email }
            onChange={ ( email ) => setAttributes( { email } ) }
          />
        </PanelBody>
      </InspectorControls>

      <article { ...useBlockProps( { className: 'celina-facility-card' } ) }>
        <div className="celina-facility-card__media">
          { normalized.imageUrl && (
            <img src={ normalized.imageUrl } alt={ normalized.imageAlt } />
          ) }
          <MediaUploadCheck>
            <MediaUpload
              allowedTypes={ [ 'image' ] }
              value={ normalized.imageId }
              onSelect={ ( media: SelectedImage ) =>
                setAttributes( {
                  imageId: typeof media.id === 'number' ? media.id : 0,
                  imageUrl: typeof media.url === 'string' ? media.url : '',
                  imageAlt: typeof media.alt === 'string' ? media.alt : '',
                } )
              }
              render={ ( { open } ) => (
                <Button variant="secondary" onClick={ open }>
                  { normalized.imageUrl ? 'Replace image' : 'Select image' }
                </Button>
              ) }
            />
          </MediaUploadCheck>
          { normalized.imageUrl && (
            <Button
              variant="tertiary"
              isDestructive
              onClick={ () =>
                setAttributes( { imageId: 0, imageUrl: '', imageAlt: '' } )
              }
            >
              Remove image
            </Button>
          ) }
        </div>
        <div className="celina-facility-card__body">
          <RichText
            tagName="h3"
            className="celina-facility-card__name"
            allowedFormats={ [] }
            value={ normalized.name }
            onChange={ ( name: string ) => setAttributes( { name } ) }
            placeholder="Facility name"
          />
          <RichContentEditor
            className="celina-facility-card__address"
            value={ normalized.address }
            onChange={ ( address: string ) => setAttributes( { address } ) }
            placeholder="Address"
          />
          <div className="celina-facility-card__contact-preview">
            { normalized.phone && <span>{ normalized.phone }</span> }
            { normalized.email && <span>{ normalized.email }</span> }
          </div>
          <div className="celina-facility-card__link-editor">
            <RichText
              tagName="span"
              allowedFormats={ [] }
              value={ normalized.label }
              onChange={ ( label: string ) => setAttributes( { label } ) }
              placeholder="Link label (optional)"
            />
            <URLInputButton
              url={ normalized.url }
              onChange={ ( url: string ) => setAttributes( { url } ) }
            />
            <LinkEditorNotice
              label={ normalized.label }
              url={ normalized.url }
            />
          </div>
        </div>
      </article>
    </>
  );
}
