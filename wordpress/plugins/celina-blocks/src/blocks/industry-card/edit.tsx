import RichContentEditor from '../../shared/rich-content-editor';
import {
  InspectorControls,
  MediaUpload,
  MediaUploadCheck,
  RichText,
  URLInputButton,
  useBlockProps,
} from '@wordpress/block-editor';
import {
  Button,
  PanelBody,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import {
  normalizeIndustryCardAttributes,
  type IndustryCardAttributes,
} from './model';
import LinkEditorNotice from '../../shared/link-editor-notice';

interface EditProps {
  attributes: IndustryCardAttributes;
  setAttributes: ( next: Partial< IndustryCardAttributes > ) => void;
}

interface SelectedImage {
  id?: number;
  url?: string;
  alt?: string;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const normalized = normalizeIndustryCardAttributes( attributes );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Card settings">
          <TextControl
            label="Image alternative text"
            value={ normalized.imageAlt }
            onChange={ ( imageAlt ) => setAttributes( { imageAlt } ) }
          />
          <ToggleControl
            label="Open link in a new tab"
            checked={ normalized.newTab }
            onChange={ ( newTab ) => setAttributes( { newTab } ) }
          />
        </PanelBody>
      </InspectorControls>

      <article { ...useBlockProps( { className: 'celina-industry-card' } ) }>
        <div className="celina-industry-card__media">
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
        <div className="celina-industry-card__body">
          <RichText
            tagName="h3"
            className="celina-industry-card__title"
            allowedFormats={ [] }
            value={ normalized.title }
            onChange={ ( title: string ) => setAttributes( { title } ) }
            placeholder="Industry"
          />
          <RichContentEditor
            className="celina-industry-card__description"
            value={ normalized.description }
            onChange={ ( description: string ) =>
              setAttributes( { description } )
            }
            placeholder="Industry description"
          />
          <div className="celina-industry-card__link-editor">
            <RichText
              tagName="span"
              allowedFormats={ [] }
              value={ normalized.linkLabel }
              onChange={ ( linkLabel: string ) =>
                setAttributes( { linkLabel } )
              }
              placeholder="Link label (optional)"
            />
            <URLInputButton
              url={ normalized.linkUrl }
              onChange={ ( linkUrl: string ) => setAttributes( { linkUrl } ) }
            />
            <LinkEditorNotice
              label={ normalized.linkLabel }
              url={ normalized.linkUrl }
            />
          </div>
        </div>
      </article>
    </>
  );
}
