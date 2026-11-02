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
  ACHIEVEMENT_TEMPLATE,
  ALLOWED_ACHIEVEMENT_BLOCKS,
  normalizeAchievementsVariant,
  type AchievementsTimelineAttributes,
  type AchievementsVariant,
} from './model';

interface EditProps {
  attributes: AchievementsTimelineAttributes;
  setAttributes: ( next: Partial< AchievementsTimelineAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  const variant = normalizeAchievementsVariant( attributes.variant );
  const ListTag = variant === 'timeline' ? 'ol' : 'ul';

  return (
    <>
      <InspectorControls>
        <PanelBody title="Section settings">
          <SelectControl
            label="Presentation"
            value={ variant }
            options={ [
              { label: 'Facts', value: 'facts' },
              { label: 'Timeline', value: 'timeline' },
              { label: 'Key figures', value: 'stats' },
            ] }
            onChange={ ( value ) =>
              setAttributes( { variant: value as AchievementsVariant } )
            }
          />
          { variant === 'stats' && (
            <p className="components-base-control__help">
              Items with a Year show it as a large figure and the Label as its
              unit. Items without a Year are listed below as proof points.
            </p>
          ) }
        </PanelBody>
      </InspectorControls>

      <section
        { ...useBlockProps( {
          className: `celina-achievements-timeline celina-achievements-timeline--${ variant } celina-collection-editor`,
        } ) }
      >
        <div className="celina-achievements-timeline__inner">
          <header className="celina-achievements-timeline__header">
            <RichText
              tagName="p"
              className="celina-achievements-timeline__eyebrow"
              value={ attributes.eyebrow }
              onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
              placeholder="Eyebrow (optional)"
            />
            <RichText
              tagName="h2"
              className="celina-achievements-timeline__heading"
              value={ attributes.heading }
              onChange={ ( heading: string ) => setAttributes( { heading } ) }
              placeholder="Achievements heading"
            />
            <RichContentEditor
              className="celina-achievements-timeline__introduction"
              value={ attributes.introduction }
              onChange={ ( introduction: string ) =>
                setAttributes( { introduction } )
              }
              placeholder="Section introduction (optional)"
            />
          </header>
          <ListTag className="celina-achievements-timeline__items">
            <InnerBlocks
              allowedBlocks={ ALLOWED_ACHIEVEMENT_BLOCKS }
              template={ ACHIEVEMENT_TEMPLATE }
              renderAppender={ InnerBlocks.ButtonBlockAppender }
            />
          </ListTag>
        </div>
      </section>
    </>
  );
}
