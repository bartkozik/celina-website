import { RichText, useBlockProps } from '@wordpress/block-editor';
import { hasFaqContent, type FaqItemAttributes } from './model';

interface SaveProps {
  attributes: FaqItemAttributes;
}

export default function save( { attributes }: SaveProps ) {
  if ( ! hasFaqContent( attributes ) ) {
    return null;
  }

  return (
    <details { ...useBlockProps.save( { className: 'celina-faq-item' } ) }>
      <RichText.Content
        tagName="summary"
        className="celina-faq-item__question"
        value={ attributes.question }
      />
      <RichText.Content
        tagName="div"
        className="celina-faq-item__answer"
        value={ attributes.answer }
      />
    </details>
  );
}
