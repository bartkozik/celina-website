export interface LinkAttributes {
  label: string;
  url: string;
  newTab: boolean;
}

export function isSafeLinkUrl( candidate: unknown ): candidate is string {
  if ( typeof candidate !== 'string' ) {
    return false;
  }

  const url = candidate.trim();
  if ( ! url || /\s/.test( url ) || url === '#' ) {
    return false;
  }

  if ( /^\/(?!\/)/.test( url ) ) {
    return true;
  }

  if ( /^#[a-z][a-z0-9_.:-]*$/i.test( url ) ) {
    return true;
  }

  if ( /^(mailto|tel):.+$/i.test( url ) ) {
    return true;
  }

  try {
    const parsed = new URL( url );
    return (
      ( parsed.protocol === 'http:' || parsed.protocol === 'https:' ) &&
      Boolean( parsed.hostname )
    );
  } catch {
    return false;
  }
}

export function normalizeLink(
  label: unknown,
  url: unknown,
  newTab: unknown
): LinkAttributes | null {
  if ( typeof label !== 'string' || typeof url !== 'string' ) {
    return null;
  }

  const normalizedLabel = label.trim();
  const normalizedUrl = url.trim();

  if ( ! normalizedLabel || ! isSafeLinkUrl( normalizedUrl ) ) {
    return null;
  }

  return {
    label: normalizedLabel,
    url: normalizedUrl,
    newTab: newTab === true,
  };
}
