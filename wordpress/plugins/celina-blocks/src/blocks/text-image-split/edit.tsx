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
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import {
  normalizeTextImageAttributes,
  type ImagePosition,
  type TextImageAttributes,
  type TextImageBackground,
} from './model';
import LinkEditorNotice from '../../shared/link-editor-notice';

interface EditProps {
  attributes: TextImageAttributes;
  setAttributes: ( next: Partial< TextImageAttributes > ) => void;
}

interface SelectedImage {
  id?: number;
  url?: string;
  alt?: string;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const normalized = normalizeTextImageAttributes( attributes );
  const className = [
    'celina-text-image-split',
    `celina-text-image-split--image-${ normalized.imagePosition }`,
    `celina-text-image-split--${ normalized.background }`,
  ].join( ' ' );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Section settings">
          <SelectControl
            label="Image position"
            value={ normalized.imagePosition }
            options={ [
              { label: 'Left', value: 'left' },
              { label: 'Right', value: 'right' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { imagePosition: value as ImagePosition } )
            }
          />
          <SelectControl
            label="Background"
            value={ normalized.background }
            options={ [
              { label: 'Neutral', value: 'neutral' },
              { label: 'Warm', value: 'warm' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { background: value as TextImageBackground } )
            }
          />
          <ToggleControl
            label="Decorative image"
            help="Decorative images are hidden from screen readers."
            checked={ normalized.imageDecorative }
            onChange={ ( imageDecorative ) =>
              setAttributes( { imageDecorative } )
            }
          />
          { ! normalized.imageDecorative && (
            <TextControl
              label="Image alternative text"
              value={ normalized.imageAlt }
              onChange={ ( imageAlt ) => setAttributes( { imageAlt } ) }
            />
          ) }
          <ToggleControl
            label="Open button link in a new tab"
            checked={ normalized.buttonNewTab }
            onChange={ ( buttonNewTab ) => setAttributes( { buttonNewTab } ) }
          />
        </PanelBody>
      </InspectorControls>

      <section { ...useBlockProps( { className } ) }>
        <div className="celina-text-image-split__inner">
          <div className="celina-text-image-split__content">
            <RichText
              tagName="p"
              className="celina-text-image-split__eyebrow"
              allowedFormats={ [] }
              value={ normalized.eyebrow }
              onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
              placeholder="Eyebrow (optional)"
            />
            <RichText
              tagName="h2"
              className="celina-text-image-split__heading"
              allowedFormats={ [] }
              value={ normalized.heading }
              onChange={ ( heading: string ) => setAttributes( { heading } ) }
              placeholder="Heading"
            />
            <RichContentEditor
              className="celina-text-image-split__prose"
              value={ normalized.content }
              onChange={ ( content: string ) => setAttributes( { content } ) }
              placeholder="Section content"
            />
            <div className="celina-text-image-split__button-editor">
              <RichText
                tagName="span"
                allowedFormats={ [] }
                value={ normalized.buttonLabel }
                onChange={ ( buttonLabel: string ) =>
                  setAttributes( { buttonLabel } )
                }
                placeholder="Button label (optional)"
              />
              <URLInputButton
                url={ normalized.buttonUrl }
                onChange={ ( buttonUrl: string ) =>
                  setAttributes( { buttonUrl } )
                }
              />
              <LinkEditorNotice
                label={ normalized.buttonLabel }
                url={ normalized.buttonUrl }
              />
            </div>
          </div>

          <div className="celina-text-image-split__media">
            { normalized.imageUrl && (
              <img
                src={ normalized.imageUrl }
                alt={ normalized.imageDecorative ? '' : normalized.imageAlt }
                aria-hidden={ normalized.imageDecorative || undefined }
              />
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
            { normalized.imageUrl &&
              ! normalized.imageDecorative &&
              ! normalized.imageAlt.trim() && (
                <p role="status">
                  Add image alternative text in Section settings, or mark this
                  image as decorative.
                </p>
              ) }
          </div>
        </div>
      </section>
    </>
  );
}
