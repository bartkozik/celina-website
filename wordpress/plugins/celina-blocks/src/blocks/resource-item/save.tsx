import { RichText, useBlockProps } from '@wordpress/block-editor';
import {
  normalizeResourceItemAttributes,
  type ResourceItemAttributes,
} from './model';

interface SaveProps {
  attributes: ResourceItemAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const normalized = normalizeResourceItemAttributes( attributes );
  const linkLabel = normalized.linkLabel.trim() || 'Download resource';

  return (
    <li { ...useBlockProps.save( { className: 'celina-resource-item' } ) }>
      <article className="celina-resource-item__body">
        <RichText.Content
          tagName="h3"
          className="celina-resource-item__title"
          value={ normalized.title }
        />
        { normalized.description && (
          <RichText.Content
            tagName="div"
            className="celina-resource-item__description"
            value={ normalized.description }
          />
        ) }
        { normalized.metadata && (
          <RichText.Content
            tagName="p"
            className="celina-resource-item__metadata"
            value={ normalized.metadata }
          />
        ) }
        { normalized.fileUrl ? (
          <a
            className="celina-resource-item__link"
            href={ normalized.fileUrl }
            download
          >
            { linkLabel }
          </a>
        ) : (
          <p className="celina-resource-item__unavailable" role="status">
            Resource file not yet available
          </p>
        ) }
      </article>
    </li>
  );
}
