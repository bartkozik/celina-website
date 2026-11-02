export type FacilityColumns = 2 | 3;

export interface FacilityCollectionAttributes
  extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  introduction: string;
  columns: FacilityColumns;
}

export const ALLOWED_FACILITY_BLOCKS: readonly string[] = [
  'celina/facility-card',
];

export const FACILITY_TEMPLATE: ReadonlyArray<
  readonly [ string, Record< string, never > ]
> = [
  [ 'celina/facility-card', {} ],
  [ 'celina/facility-card', {} ],
];

export function normalizeFacilityColumns( value: unknown ): FacilityColumns {
  return value === 3 ? 3 : 2;
}
