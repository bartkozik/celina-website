export function normalizeLegacyLists( html: string ): string {
  const document = new DOMParser().parseFromString( html, 'text/html' );
  let changed = false;
  for ( const item of document.body.querySelectorAll( 'li' ) ) {
    const parent = item.parentElement;
    if ( ! parent || [ 'UL', 'OL' ].includes( parent.tagName ) ) {
      continue;
    }
    const list = document.createElement( 'ul' );
    parent.insertBefore( list, item );
    let sibling: ChildNode | null = item;
    while ( sibling ) {
      const isItem =
        sibling.nodeType === Node.ELEMENT_NODE &&
        ( sibling as Element ).tagName === 'LI';
      const isSpacing =
        sibling.nodeType === Node.TEXT_NODE && ! sibling.textContent?.trim();
      if ( ! isItem && ! isSpacing && sibling.nodeType !== Node.COMMENT_NODE ) {
        break;
      }
      const next: ChildNode | null = sibling.nextSibling;
      list.appendChild( sibling );
      sibling = next;
    }
    changed = true;
  }
  return changed ? document.body.innerHTML : html;
}
