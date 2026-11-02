export type ResourceVariant = 'standard' | 'featured';

export interface ResourceListAttributes extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  introduction: string;
  variant: ResourceVariant;
}

export const ALLOWED_RESOURCE_BLOCKS: readonly string[] = [
  'celina/resource-item',
];

export const RESOURCE_TEMPLATE: ReadonlyArray<
  readonly [ string, Record< string, never > ]
> = [
  [ 'celina/resource-item', {} ],
  [ 'celina/resource-item', {} ],
  [ 'celina/resource-item', {} ],
];

export function normalizeResourceVariant( value: unknown ): ResourceVariant {
  return value === 'featured' ? 'featured' : 'standard';
}
