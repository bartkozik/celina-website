import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
import {
  normalizeAchievementsVariant,
  type AchievementsTimelineAttributes,
} from './model';

interface SaveProps {
  attributes: AchievementsTimelineAttributes;
}

export default function save( { attributes }: SaveProps ) {
  const variant = normalizeAchievementsVariant( attributes.variant );
  const ListTag = variant === 'timeline' ? 'ol' : 'ul';

  return (
    <section
      { ...useBlockProps.save( {
        className: `celina-achievements-timeline celina-achievements-timeline--${ variant }`,
      } ) }
    >
      <div className="celina-achievements-timeline__inner">
        <header className="celina-achievements-timeline__header">
          { attributes.eyebrow && (
            <RichText.Content
              tagName="p"
              className="celina-achievements-timeline__eyebrow"
              value={ attributes.eyebrow }
            />
          ) }
          <RichText.Content
            tagName="h2"
            className="celina-achievements-timeline__heading"
            value={ attributes.heading }
          />
          { attributes.introduction && (
            <RichText.Content
              tagName="div"
              className="celina-achievements-timeline__introduction"
              value={ attributes.introduction }
            />
          ) }
        </header>
        <ListTag className="celina-achievements-timeline__items">
          <InnerBlocks.Content />
        </ListTag>
      </div>
    </section>
  );
}
