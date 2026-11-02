export type CapabilityVariant = 'light' | 'dark';
export type CapabilityColumns = 2 | 3 | 4;

export interface CapabilityCollectionAttributes
  extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  introduction: string;
  variant: CapabilityVariant;
  columns: CapabilityColumns;
}

export const ALLOWED_CAPABILITY_BLOCKS: readonly string[] = [
  'celina/capability-card',
];

export const CAPABILITY_TEMPLATE: ReadonlyArray<
  readonly [ string, Record< string, never > ]
> = [
  [ 'celina/capability-card', {} ],
  [ 'celina/capability-card', {} ],
  [ 'celina/capability-card', {} ],
];

export function normalizeCapabilityVariant(
  value: unknown
): CapabilityVariant {
  return value === 'dark' ? 'dark' : 'light';
}

export function normalizeCapabilityColumns(
  value: unknown
): CapabilityColumns {
  return value === 2 || value === 4 ? value : 3;
}
