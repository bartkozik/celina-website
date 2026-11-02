import { normalizeLink, type LinkAttributes } from '../../shared/link';

export type ImagePosition = 'left' | 'right';
export type TextImageBackground = 'neutral' | 'warm';

export interface TextImageAttributes extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  content: string;
  imageId: number;
  imageUrl: string;
  imageAlt: string;
  imageDecorative: boolean;
  imagePosition: ImagePosition;
  background: TextImageBackground;
  buttonLabel: string;
  buttonUrl: string;
  buttonNewTab: boolean;
}

function normalizeString( value: unknown ): string {
  return typeof value === 'string' ? value : '';
}

export function normalizeImageId( imageId: unknown ): number {
  if ( typeof imageId !== 'number' || ! Number.isFinite( imageId ) ) {
    return 0;
  }

  return Math.max( 0, Math.trunc( imageId ) );
}

export function normalizeTextImageAttributes(
  attributes: Partial< Record< keyof TextImageAttributes, unknown > > | null
): TextImageAttributes {
  const candidate = attributes ?? {};

  return {
    eyebrow: normalizeString( candidate.eyebrow ),
    heading: normalizeString( candidate.heading ),
    content: normalizeString( candidate.content ),
    imageId: normalizeImageId( candidate.imageId ),
    imageUrl: normalizeString( candidate.imageUrl ),
    imageAlt: normalizeString( candidate.imageAlt ),
    imageDecorative: candidate.imageDecorative === true,
    imagePosition: candidate.imagePosition === 'left' ? 'left' : 'right',
    background: candidate.background === 'warm' ? 'warm' : 'neutral',
    buttonLabel: normalizeString( candidate.buttonLabel ),
    buttonUrl: normalizeString( candidate.buttonUrl ),
    buttonNewTab: candidate.buttonNewTab === true,
  };
}

export function getRenderableButton(
  attributes: TextImageAttributes
): LinkAttributes | null {
  return normalizeLink(
    attributes.buttonLabel,
    attributes.buttonUrl,
    attributes.buttonNewTab
  );
}
