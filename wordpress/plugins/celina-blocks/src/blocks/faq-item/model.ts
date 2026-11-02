export interface FaqItemAttributes extends Record< string, unknown > {
  question: string;
  answer: string;
}

const INVISIBLE_CHARACTERS =
  /[\p{White_Space}\p{Default_Ignorable_Code_Point}\p{Cc}]+/gu;
const NON_RENDERED_ELEMENT_SELECTOR =
  'script, style, template, noscript, title, [hidden], dialog:not([open])';

function getRenderedText( value: string ): string {
  if ( typeof document === 'undefined' ) {
    return '';
  }

  const container = document.createElement( 'div' );
  container.innerHTML = value;
  container
    .querySelectorAll( NON_RENDERED_ELEMENT_SELECTOR )
    .forEach( ( element ) => element.remove() );

  return container.textContent ?? '';
}

function hasVisibleText( value: string ): boolean {
  return (
    getRenderedText( value ).replace( INVISIBLE_CHARACTERS, '' ).length > 0
  );
}

export function hasFaqContent( attributes: unknown ): boolean {
  if ( ! attributes || typeof attributes !== 'object' ) {
    return false;
  }

  const { question, answer } = attributes as Partial< FaqItemAttributes >;

  return (
    typeof question === 'string' &&
    hasVisibleText( question ) &&
    typeof answer === 'string' &&
    hasVisibleText( answer )
  );
}
