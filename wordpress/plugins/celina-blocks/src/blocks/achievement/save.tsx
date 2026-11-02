import { RichText, useBlockProps } from '@wordpress/block-editor';
import {
  hasAchievementContent,
  normalizeAchievementIcon,
  type AchievementAttributes,
} from './model';

interface SaveProps {
  attributes: AchievementAttributes;
}

export default function save( { attributes }: SaveProps ) {
  if ( ! hasAchievementContent( attributes ) ) {
    return null;
  }

  const icon = normalizeAchievementIcon( attributes.icon );

  return (
    <li { ...useBlockProps.save( { className: 'celina-achievement' } ) }>
      { icon && (
        <span
          aria-hidden="true"
          className={ `celina-achievement__icon celina-achievement__icon--${ icon }` }
        />
      ) }
      { ( attributes.prefix || attributes.year || attributes.label ) && (
        <div className="celina-achievement__marker">
          { attributes.prefix && (
            <RichText.Content
              tagName="span"
              className="celina-achievement__prefix"
              value={ attributes.prefix }
            />
          ) }
          { attributes.year && (
            <RichText.Content
              tagName="span"
              className="celina-achievement__year"
              value={ attributes.year }
            />
          ) }
          { attributes.label && (
            <RichText.Content
              tagName="span"
              className="celina-achievement__label"
              value={ attributes.label }
            />
          ) }
        </div>
      ) }
      <RichText.Content
        tagName="h3"
        className="celina-achievement__heading"
        value={ attributes.heading }
      />
      { attributes.content && (
        <RichText.Content
          tagName="div"
          className="celina-achievement__content"
          value={ attributes.content }
        />
      ) }
    </li>
  );
}
