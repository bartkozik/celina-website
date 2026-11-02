import '../../shared/collection-editor.scss';
import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
import { ALLOWED_FAQ_BLOCKS, FAQ_TEMPLATE, type FaqAttributes } from './model';

interface EditProps {
  attributes: FaqAttributes;
  setAttributes: ( next: Partial< FaqAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  return (
    <section
      { ...useBlockProps( {
        className: 'celina-faq celina-collection-editor',
      } ) }
    >
      <header className="celina-faq__header">
        <RichText
          tagName="p"
          className="celina-faq__eyebrow"
          value={ attributes.eyebrow }
          onChange={ ( eyebrow: string ) => setAttributes( { eyebrow } ) }
          placeholder="FAQ eyebrow (optional)"
        />
        <RichText
          tagName="h2"
          className="celina-faq__heading"
          value={ attributes.heading }
          onChange={ ( heading: string ) => setAttributes( { heading } ) }
          placeholder="FAQ heading (optional)"
        />
      </header>
      <InnerBlocks
        allowedBlocks={ ALLOWED_FAQ_BLOCKS }
        template={ FAQ_TEMPLATE }
        renderAppender={ InnerBlocks.ButtonBlockAppender }
      />
    </section>
  );
}
