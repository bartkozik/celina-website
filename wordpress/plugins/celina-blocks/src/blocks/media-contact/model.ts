export interface MediaContactAttributes extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  name: string;
  role: string;
  email: string;
  phone: string;
}

export function normalizeEmail( value: string ): string {
  const email = value.trim();

  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ? email : '';
}

export function getPhoneHref( value: string ): string {
  const phone = value.trim().replace( /[\s().-]/g, '' );

  return /^\+?[0-9]{7,15}$/.test( phone ) ? `tel:${ phone }` : '';
}
