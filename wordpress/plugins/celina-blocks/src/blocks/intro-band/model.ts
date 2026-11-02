export type IntroBandVariant = 'neutral' | 'warm';

export interface IntroBandAttributes extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  content: string;
  variant: IntroBandVariant;
}

export function normalizeVariant( variant: unknown ): IntroBandVariant {
  return variant === 'warm' ? 'warm' : 'neutral';
}
