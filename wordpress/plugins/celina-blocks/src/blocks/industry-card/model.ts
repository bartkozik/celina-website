import { normalizeImageId } from '../text-image-split/model';

export interface IndustryCardAttributes extends Record< string, unknown > {
  imageId: number;
  imageUrl: string;
  imageAlt: string;
  title: string;
  description: string;
  linkLabel: string;
  linkUrl: string;
  newTab: boolean;
}

function normalizeString( value: unknown ): string {
  return typeof value === 'string' ? value : '';
}

export function normalizeIndustryCardAttributes(
  attributes: Partial< Record< keyof IndustryCardAttributes, unknown > > | null
): IndustryCardAttributes {
  const candidate = attributes ?? {};

  return {
    imageId: normalizeImageId( candidate.imageId ),
    imageUrl: normalizeString( candidate.imageUrl ),
    imageAlt: normalizeString( candidate.imageAlt ),
    title: normalizeString( candidate.title ),
    description: normalizeString( candidate.description ),
    linkLabel: normalizeString( candidate.linkLabel ),
    linkUrl: normalizeString( candidate.linkUrl ),
    newTab: candidate.newTab === true,
  };
}
