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
} from '@wordpress/components';
import {
  getEditableButtons,
  type HeroAttributes,
  type HeroButton,
  type HeroVariant,
} from './model';
import { normalizeLink } from '../../shared/link';
import LinkEditorNotice from '../../shared/link-editor-notice';

interface EditProps {
  attributes: HeroAttributes;
  setAttributes: ( next: Partial< HeroAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const variant: HeroVariant =
    attributes.variant === 'careers' ? 'careers' : 'standard';
  const buttons = getEditableButtons( attributes.buttons );
  const previewButtons = buttons.flatMap( ( button ) => {
    const link = normalizeLink( button.label, button.url, false );
    return link ? [ link ] : [];
  } );
  const className = `celina-hero celina-hero--${ variant }`;
  const blockProps = useBlockProps( {
    className,
    style: attributes.backgroundUrl
      ? { backgroundImage: `url(${ attributes.backgroundUrl })` }
      : undefined,
  } );
  const updateButton = ( index: number, next: Partial< HeroButton > ) => {
    setAttributes( {
      buttons: buttons.map( ( button, buttonIndex ) =>
        buttonIndex === index ? { ...button, ...next } : button
      ),
    } );
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title="Hero settings">
          <SelectControl
            label="Variant"
            value={ variant }
            options={ [
              { label: 'Standard', value: 'standard' },
              { label: 'Careers', value: 'careers' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { variant: value as HeroVariant } )
            }
          />
          <MediaUploadCheck>
            <MediaUpload
              allowedTypes={ [ 'image' ] }
              value={ attributes.backgroundId }
              onSelect={ ( media ) =>
                setAttributes( {
                  backgroundId: media.id,
                  backgroundUrl: media.url,
                } )
              }
              render={ ( { open } ) => (
                <Button variant="secondary" onClick={ open }>
                  { attributes.backgroundUrl
                    ? 'Replace background image'
                    : 'Select background image' }
                </Button>
              ) }
            />
          </MediaUploadCheck>
          { attributes.backgroundUrl && (
            <Button
              variant="tertiary"
              isDestructive
              onClick={ () =>
                setAttributes( { backgroundId: 0, backgroundUrl: '' } )
              }
            >
              Remove background image
            </Button>
          ) }
          <p className="components-base-control__help">
            The background is decorative. Put its meaning in the heading and
            description.
          </p>

          { buttons.map( ( button, index ) => (
            <div className="celina-hero__button-control" key={ index }>
              <TextControl
                label={ `Button ${ index + 1 } label` }
                value={ button.label }
                onChange={ ( label ) => updateButton( index, { label } ) }
              />
              <URLInputButton
                url={ button.url }
                onChange={ ( url ) => updateButton( index, { url } ) }
              />
              <LinkEditorNotice label={ button.label } url={ button.url } />
              <Button
                isDestructive
                onClick={ () =>
                  setAttributes( {
                    buttons: buttons.filter(
                      ( _, buttonIndex ) => buttonIndex !== index
                    ),
                  } )
                }
              >
                Remove button
              </Button>
            </div>
          ) ) }

          { buttons.length < 2 && (
            <Button
              variant="secondary"
              onClick={ () =>
                setAttributes( {
                  buttons: [ ...buttons, { label: '', url: '' } ],
                } )
              }
            >
              Add button
            </Button>
          ) }
        </PanelBody>
      </InspectorControls>

      <section { ...blockProps }>
        { attributes.backgroundUrl && (
          <div className="celina-hero__overlay" aria-hidden="true" />
        ) }
        <div className="celina-hero__inner">
          <RichText
            tagName="p"
            className="celina-hero__eyebrow"
            value={ attributes.eyebrow }
            onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
            placeholder="Eyebrow"
          />
          <RichText
            tagName="h1"
            className="celina-hero__heading"
            value={ attributes.heading }
            onChange={ ( heading: string ) => setAttributes( { heading } ) }
            placeholder="Hero heading"
          />
          <RichText
            tagName="p"
            className="celina-hero__description"
            value={ attributes.description }
            onChange={ ( description: string ) =>
              setAttributes( { description } )
            }
            placeholder="Hero description"
          />
          { previewButtons.length > 0 && (
            <div className="celina-hero__actions" aria-label="Button preview">
              { previewButtons.map( ( button, index ) => (
                <span className="celina-button" key={ index }>
                  { button.label }
                </span>
              ) ) }
            </div>
          ) }
          { buttons.length > 0 && (
            <p className="celina-hero__editor-help">
              Edit these buttons in the Hero settings sidebar.
            </p>
          ) }
        </div>
      </section>
    </>
  );
}
