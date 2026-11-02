export type IndustryColumns = 1 | 2 | 3 | 4;

export interface IndustryCollectionAttributes
  extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  introduction: string;
  columns: IndustryColumns;
}

export const ALLOWED_INDUSTRY_BLOCKS: readonly string[] = [
  'celina/industry-card',
];

export const INDUSTRY_TEMPLATE: ReadonlyArray<
  readonly [ string, Record< string, never > ]
> = [
  [ 'celina/industry-card', {} ],
  [ 'celina/industry-card', {} ],
  [ 'celina/industry-card', {} ],
];

export function normalizeIndustryColumns( value: unknown ): IndustryColumns {
  return value === 1 || value === 2 || value === 4 ? value : 3;
}
