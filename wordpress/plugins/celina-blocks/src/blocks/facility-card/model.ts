export interface FacilityCardAttributes extends Record< string, unknown > {
  imageId: number;
  imageUrl: string;
  imageAlt: string;
  name: string;
  address: string;
  phone: string;
  email: string;
  label: string;
  url: string;
}

function normalizeString( value: unknown ): string {
  return typeof value === 'string' ? value : '';
}

export function normalizeFacilityCardAttributes(
  attributes: Record< string, unknown >
): FacilityCardAttributes {
  const imageId =
    typeof attributes.imageId === 'number' &&
    Number.isInteger( attributes.imageId ) &&
    attributes.imageId > 0
      ? attributes.imageId
      : 0;

  return {
    imageId,
    imageUrl: normalizeString( attributes.imageUrl ),
    imageAlt: normalizeString( attributes.imageAlt ),
    name: normalizeString( attributes.name ),
    address: normalizeString( attributes.address ),
    phone: normalizeString( attributes.phone ),
    email: normalizeString( attributes.email ),
    label: normalizeString( attributes.label ),
    url: normalizeString( attributes.url ),
  };
}
