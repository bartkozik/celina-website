import RichContentEditor from '../../shared/rich-content-editor';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import type { FaqItemAttributes } from './model';

interface EditProps {
  attributes: FaqItemAttributes;
  setAttributes: ( next: Partial< FaqItemAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
  return (
    <div { ...useBlockProps( { className: 'celina-faq-item' } ) }>
      <RichText
        tagName="h3"
        value={ attributes.question }
        onChange={ ( question ) => setAttributes( { question } ) }
        placeholder="Question"
      />
      <RichContentEditor
        value={ attributes.answer }
        onChange={ ( answer ) => setAttributes( { answer } ) }
        placeholder="Answer"
      />
    </div>
  );
}
