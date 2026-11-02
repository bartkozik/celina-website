export interface ResourceItemAttributes extends Record< string, unknown > {
  title: string;
  description: string;
  metadata: string;
  fileId: number;
  fileUrl: string;
  linkLabel: string;
}

function normalizeString( value: unknown ): string {
  return typeof value === 'string' ? value : '';
}

export function isSafeResourceFileUrl( candidate: unknown ): boolean {
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

export function normalizeResourceItemAttributes(
  attributes: Record< string, unknown >
): ResourceItemAttributes {
  const fileId =
    typeof attributes.fileId === 'number' &&
    Number.isInteger( attributes.fileId ) &&
    attributes.fileId > 0
      ? attributes.fileId
      : 0;
  const rawFileUrl = normalizeString( attributes.fileUrl );

  return {
    title: normalizeString( attributes.title ),
    description: normalizeString( attributes.description ),
    metadata: normalizeString( attributes.metadata ),
    fileId,
    fileUrl: isSafeResourceFileUrl( rawFileUrl ) ? rawFileUrl.trim() : '',
    linkLabel: normalizeString( attributes.linkLabel ),
  };
}
