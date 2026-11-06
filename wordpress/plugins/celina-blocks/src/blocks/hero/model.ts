export interface HeroButton {
  label: string;
  url: string;
}

export type HeroVariant = 'standard' | 'careers';

export interface HeroAttributes extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  description: string;
  buttons: HeroButton[];
  backgroundId?: number;
  backgroundUrl: string;
  variant: HeroVariant;
}

function getStructurallyValidButtons( buttons: unknown ): HeroButton[] {
  if ( ! Array.isArray( buttons ) ) {
    return [];
  }

  return buttons.filter(
    ( button: unknown ): button is HeroButton =>
      button !== null &&
      typeof button === 'object' &&
      'label' in button &&
      'url' in button &&
      typeof button.label === 'string' &&
      typeof button.url === 'string'
  );
}

export function getEditableButtons( buttons: unknown ): HeroButton[] {
  return getStructurallyValidButtons( buttons ).slice( 0, 2 );
}

export function normalizeButtons(
  buttons: unknown,
  variant: unknown = 'standard'
): HeroButton[] {
  if ( variant !== 'standard' && variant !== 'careers' ) {
    throw new Error( 'invalid Hero variant' );
  }

  return getStructurallyValidButtons( buttons )
    .flatMap( ( button ) => {
      const label = button.label.trim();
      const url = button.url.trim();

      return label && url ? [ { label, url } ] : [];
    } )
    .slice( 0, 2 );
}
