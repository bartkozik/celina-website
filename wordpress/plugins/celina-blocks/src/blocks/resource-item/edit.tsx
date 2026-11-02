import RichContentEditor from '../../shared/rich-content-editor';
import {
  MediaUpload,
  MediaUploadCheck,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import { Button, TextControl } from '@wordpress/components';
import { useEffect, useState } from 'react';
import {
  isSafeResourceFileUrl,
  normalizeResourceItemAttributes,
  type ResourceItemAttributes,
} from './model';

export const DOCUMENT_MIME_TYPES = [
  'application/zip',
  'image/png',
  'image/jpeg',
  'image/webp',
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];

interface EditProps {
  attributes: ResourceItemAttributes;
  setAttributes: ( next: Partial< ResourceItemAttributes > ) => void;
}

interface SelectedDocument {
  id?: number;
  url?: string;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const normalized = normalizeResourceItemAttributes( attributes );
  const [ fileUrlDraft, setFileUrlDraft ] = useState( normalized.fileUrl );
  const [ urlError, setUrlError ] = useState( '' );

  useEffect( () => {
    setFileUrlDraft( normalized.fileUrl );
    setUrlError( '' );
  }, [ normalized.fileUrl ] );

  const applyFileUrl = () => {
    const fileUrl = fileUrlDraft.trim();
    if ( ! isSafeResourceFileUrl( fileUrl ) ) {
      setUrlError( 'Enter a valid https:// URL or a site-relative file path.' );
      return;
    }
    setUrlError( '' );
    setAttributes( { fileId: 0, fileUrl } );
  };

  return (
    <li { ...useBlockProps( { className: 'celina-resource-item' } ) }>
      <article className="celina-resource-item__body">
        <RichText
          tagName="h3"
          className="celina-resource-item__title"
          value={ normalized.title }
          onChange={ ( title: string ) => setAttributes( { title } ) }
          placeholder="Resource title"
        />
        <RichContentEditor
          className="celina-resource-item__description"
          value={ normalized.description }
          onChange={ ( description: string ) =>
            setAttributes( { description } )
          }
          placeholder="Resource description"
        />
        <RichText
          tagName="p"
          className="celina-resource-item__metadata"
          value={ normalized.metadata }
          onChange={ ( metadata: string ) => setAttributes( { metadata } ) }
          placeholder="Type and size (optional)"
        />
        <RichText
          tagName="span"
          allowedFormats={ [] }
          className="celina-resource-item__link-label"
          value={ normalized.linkLabel }
          onChange={ ( linkLabel: string ) => setAttributes( { linkLabel } ) }
          placeholder="Download link label"
        />
        <div className="celina-resource-item__file-control">
          <MediaUploadCheck>
            <MediaUpload
              allowedTypes={ DOCUMENT_MIME_TYPES }
              value={ normalized.fileId }
              onSelect={ ( media: SelectedDocument ) =>
                setAttributes( {
                  fileId: typeof media.id === 'number' ? media.id : 0,
                  fileUrl: typeof media.url === 'string' ? media.url : '',
                } )
              }
              render={ ( { open } ) => (
                <Button variant="secondary" onClick={ open }>
                  { normalized.fileUrl
                    ? 'Replace resource file'
                    : 'Select resource file' }
                </Button>
              ) }
            />
          </MediaUploadCheck>
          { normalized.fileUrl ? (
            <Button
              variant="tertiary"
              isDestructive
              onClick={ () => setAttributes( { fileId: 0, fileUrl: '' } ) }
            >
              Remove resource file
            </Button>
          ) : (
            <p role="status">Resource file not yet available</p>
          ) }
        </div>
        { normalized.fileUrl && (
          <p className="celina-resource-item__selected-file">
            Selected file: { normalized.fileUrl }
          </p>
        ) }
        <div className="celina-resource-item__url-control">
          <TextControl
            label="Resource file URL"
            help="Choose a file above, or paste the address of an existing download. PDF, Office documents, ZIP and image files are supported."
            value={ fileUrlDraft }
            onChange={ ( value ) => {
              setFileUrlDraft( value );
              setUrlError( '' );
            } }
          />
          <Button variant="secondary" onClick={ applyFileUrl }>
            Apply file URL
          </Button>
          { urlError && <p role="alert">{ urlError }</p> }
          { ! urlError && fileUrlDraft.trim() !== normalized.fileUrl && (
            <p role="status">
              Choose Apply file URL to use this address. The current download
              stays unchanged until then.
            </p>
          ) }
        </div>
      </article>
    </li>
  );
}
