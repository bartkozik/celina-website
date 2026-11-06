import RichContentEditor from '../../shared/rich-content-editor';
import {
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import {
  ACHIEVEMENT_ICONS,
  normalizeAchievementIcon,
  type AchievementAttributes,
} from './model';

interface EditProps {
  attributes: AchievementAttributes;
  setAttributes: ( next: Partial< AchievementAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const icon = normalizeAchievementIcon( attributes.icon );
  const classNames = [ 'celina-achievement' ];

  if ( icon ) {
    classNames.push( 'celina-achievement--has-icon' );
  } else if ( ! attributes.prefix && ! attributes.year && ! attributes.label ) {
    classNames.push( 'celina-achievement--no-figure' );
  }

  return (
    <>
      <InspectorControls>
        <PanelBody title="Achievement settings">
          <SelectControl
            label="Icon"
            value={ icon }
            options={ [ ...ACHIEVEMENT_ICONS ] }
            onChange={ ( value ) =>
              setAttributes( { icon: normalizeAchievementIcon( value ) } )
            }
          />
        </PanelBody>
      </InspectorControls>

      <li { ...useBlockProps( { className: classNames.join( ' ' ) } ) }>
        { icon && (
          <span
            aria-hidden="true"
            className={ `celina-achievement__icon celina-achievement__icon--${ icon }` }
          />
        ) }
        <div className="celina-achievement__marker">
          <RichText
            tagName="span"
            className="celina-achievement__prefix"
            value={ attributes.prefix ?? '' }
            onChange={ ( prefix: string ) => setAttributes( { prefix } ) }
            placeholder="Prefix (optional)"
          />
          <RichText
            tagName="span"
            className="celina-achievement__year"
            value={ attributes.year }
            onChange={ ( year: string ) => setAttributes( { year } ) }
            placeholder="Year (optional)"
          />
          <RichText
            tagName="span"
            className="celina-achievement__label"
            value={ attributes.label }
            onChange={ ( label: string ) => setAttributes( { label } ) }
            placeholder="Label (optional)"
          />
        </div>
        <RichText
          tagName="h3"
          className="celina-achievement__heading"
          value={ attributes.heading }
          onChange={ ( heading: string ) => setAttributes( { heading } ) }
          placeholder="Achievement"
        />
        <RichContentEditor
          className="celina-achievement__content"
          value={ attributes.content }
          onChange={ ( content: string ) => setAttributes( { content } ) }
          placeholder="Achievement description"
        />
      </li>
    </>
  );
}
